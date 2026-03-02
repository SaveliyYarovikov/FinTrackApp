<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\CsvImportResult;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use SplFileObject;
use Throwable;

class CsvImportService
{
    private const TYPE_EXPENSE_RU = 'Списание';
    private const TYPE_INCOME_RU = 'Пополнение';

    /**
     * @var array<string, Account>
     */
    private array $accountsByName = [];

    /**
     * @var array<string, Category>
     */
    private array $categoriesByKey = [];

    private ?bool $hasCategoryTypeColumn = null;

    private ?bool $hasCategoryIsSystemColumn = null;

    public function __construct(private readonly TransactionService $transactionService)
    {
    }

    public function import(User $user, UploadedFile $file, bool $skipDuplicates = true): CsvImportResult
    {
        $this->accountsByName = [];
        $this->categoriesByKey = [];

        $result = new CsvImportResult();

        try {
            $csv = new SplFileObject($file->getRealPath() ?: $file->path(), 'r');
        } catch (Throwable) {
            $result->addError(1, 'Unable to read CSV file.');

            return $result;
        }

        $delimiter = $this->detectDelimiter($csv);

        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $csv->setCsvControl($delimiter);
        $csv->rewind();

        $header = $csv->fgetcsv();

        if (! is_array($header)) {
            $result->addError(1, 'CSV header is missing.');

            return $result;
        }

        $headerMap = $this->buildHeaderMap($header);
        $requiredColumns = ['accountName', 'category', 'operationDate', 'amount', 'type', 'merchant'];
        $missingColumns = array_values(array_filter(
            $requiredColumns,
            static fn (string $column): bool => ! array_key_exists($column, $headerMap),
        ));

        if ($missingColumns !== []) {
            $result->addError(1, 'Missing required columns: '.implode(', ', $missingColumns));

            return $result;
        }

        $lineNumber = 1;

        while (! $csv->eof()) {
            $row = $csv->fgetcsv();
            ++$lineNumber;

            if (! is_array($row) || $this->isEmptyRow($row)) {
                continue;
            }

            ++$result->totalRows;

            try {
                $this->importRow($user, $row, $headerMap, $skipDuplicates, $result);
            } catch (Throwable $exception) {
                ++$result->skippedRows;
                $result->addError($lineNumber, $exception->getMessage());
            }
        }

        return $result;
    }

    /**
     * @param array<int, string|null> $header
     * @return array<string, int>
     */
    private function buildHeaderMap(array $header): array
    {
        if (isset($header[0]) && is_string($header[0])) {
            $header[0] = $this->stripBom($header[0]);
        }

        $map = [];

        foreach ($header as $index => $columnName) {
            $normalized = trim((string) $columnName);

            if ($normalized === '') {
                continue;
            }

            $map[$normalized] = $index;
        }

        return $map;
    }

    /**
     * @param array<int, string|null> $row
     * @param array<string, int> $headerMap
     */
    private function importRow(
        User $user,
        array $row,
        array $headerMap,
        bool $skipDuplicates,
        CsvImportResult $result,
    ): void {
        $accountName = $this->requiredCell($row, $headerMap, 'accountName');
        $categoryName = $this->requiredCell($row, $headerMap, 'category');
        $operationDate = $this->requiredCell($row, $headerMap, 'operationDate');
        $amount = $this->requiredCell($row, $headerMap, 'amount');
        $operationType = $this->requiredCell($row, $headerMap, 'type');

        $merchant = $this->cell($row, $headerMap, 'merchant');
        $description = $merchant === '' ? null : $merchant;

        [$transactionType, $categoryType] = $this->resolveTypes($operationType);

        $occurredAt = $this->parseOccurredAt($operationDate);

        try {
            $amountMinor = abs(Money::parseMajorToMinor($amount));
        } catch (InvalidArgumentException $exception) {
            throw new DomainException('Invalid amount: '.$exception->getMessage());
        }

        if ($amountMinor <= 0) {
            throw new DomainException('Amount must be positive.');
        }

        $account = $this->resolveAccount($user, $accountName);
        $category = $this->resolveCategory($user, $categoryName, $categoryType);

        $storedAmountMinor = $transactionType === Transaction::TYPE_EXPENSE
            ? -$amountMinor
            : $amountMinor;

        if ($skipDuplicates && $this->isDuplicate($user, $account->id, $occurredAt, $storedAmountMinor, $description)) {
            ++$result->skippedRows;

            return;
        }

        if ($transactionType === Transaction::TYPE_EXPENSE) {
            $this->createExpense($user, $account, $category, $amountMinor, $occurredAt, $description);
        } else {
            $this->transactionService->createIncome(
                $user,
                $account,
                $category,
                $amountMinor,
                $occurredAt,
                $description,
            );
        }

        ++$result->importedRows;
    }

    /**
     * @param array<int, string|null> $row
     * @param array<string, int> $headerMap
     */
    private function cell(array $row, array $headerMap, string $column): string
    {
        $index = $headerMap[$column] ?? null;

        if ($index === null) {
            return '';
        }

        return trim((string) ($row[$index] ?? ''));
    }

    /**
     * @param array<int, string|null> $row
     * @param array<string, int> $headerMap
     */
    private function requiredCell(array $row, array $headerMap, string $column): string
    {
        $value = $this->cell($row, $headerMap, $column);

        if ($value === '') {
            throw new DomainException(sprintf('Column "%s" is required.', $column));
        }

        return $value;
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function resolveTypes(string $csvType): array
    {
        return match (trim($csvType)) {
            self::TYPE_EXPENSE_RU => [Transaction::TYPE_EXPENSE, Category::TYPE_EXPENSE],
            self::TYPE_INCOME_RU => [Transaction::TYPE_INCOME, Category::TYPE_INCOME],
            default => throw new DomainException(sprintf('Unsupported type "%s".', $csvType)),
        };
    }

    private function parseOccurredAt(string $value): string
    {
        try {
            $date = Carbon::createFromFormat('d.m.Y', $value);
        } catch (Throwable $exception) {
            throw new DomainException('Invalid operationDate format. Expected dd.mm.yyyy.');
        }

        if ($date === false || $date->format('d.m.Y') !== $value) {
            throw new DomainException('Invalid operationDate format. Expected dd.mm.yyyy.');
        }

        return $date
            ->startOfDay()
            ->format('Y-m-d H:i:s');
    }

    private function resolveAccount(User $user, string $accountName): Account
    {
        $key = $this->normalizeLookupKey($accountName);

        if (isset($this->accountsByName[$key])) {
            return $this->accountsByName[$key];
        }

        $account = Account::query()
            ->where('user_id', $user->id)
            ->where('name', $accountName)
            ->first();

        if ($account === null) {
            try {
                $account = Account::query()->create([
                    'user_id' => $user->id,
                    'name' => $accountName,
                    'type' => Account::TYPE_CARD,
                    'currency' => 'RUB',
                    'opening_balance_minor' => 0,
                    'balance_minor' => 0,
                ]);
            } catch (QueryException) {
                $account = Account::query()
                    ->where('user_id', $user->id)
                    ->where('name', $accountName)
                    ->first();

                if ($account === null) {
                    throw new DomainException('Failed to resolve account.');
                }
            }
        }

        $this->accountsByName[$key] = $account;

        return $account;
    }

    private function resolveCategory(User $user, string $categoryName, string $categoryType): Category
    {
        $key = $categoryType.'|'.$this->normalizeLookupKey($categoryName);

        if (isset($this->categoriesByKey[$key])) {
            return $this->categoriesByKey[$key];
        }

        $query = Category::query()
            ->forUser($user->id)
            ->where('name', $categoryName);

        if ($this->categoryTypeColumnExists()) {
            $query->where('type', $categoryType);
        }

        $category = $query->first();

        if ($category === null) {
            $attributes = [
                'user_id' => $user->id,
                'name' => $categoryName,
            ];

            if ($this->categoryTypeColumnExists()) {
                $attributes['type'] = $categoryType;
            }

            if ($this->categoryIsSystemColumnExists()) {
                $attributes['is_system'] = false;
            }

            try {
                $category = Category::query()->create($attributes);
            } catch (QueryException) {
                $retryQuery = Category::query()
                    ->forUser($user->id)
                    ->where('name', $categoryName);

                if ($this->categoryTypeColumnExists()) {
                    $retryQuery->where('type', $categoryType);
                }

                $category = $retryQuery->first();

                if ($category === null) {
                    throw new DomainException('Failed to resolve category.');
                }
            }
        }

        $this->categoriesByKey[$key] = $category;

        return $category;
    }

    private function isDuplicate(
        User $user,
        int $accountId,
        string $occurredAt,
        int $amountMinor,
        ?string $description,
    ): bool {
        $query = Transaction::query()
            ->where('user_id', $user->id)
            ->where('account_id', $accountId)
            ->where('occurred_at', $occurredAt)
            ->where('amount_minor', $amountMinor);

        if ($description === null) {
            $query->whereNull('description');
        } else {
            $query->where('description', $description);
        }

        return $query->exists();
    }

    private function createExpense(
        User $user,
        Account $account,
        Category $category,
        int $amountMinor,
        string $occurredAt,
        ?string $description,
    ): void {
        try {
            $this->transactionService->createExpense(
                $user,
                $account,
                $category,
                $amountMinor,
                $occurredAt,
                $description,
            );
        } catch (DomainException $exception) {
            if (! $this->looksLikeExpenseSignError($exception->getMessage())) {
                throw $exception;
            }

            $this->transactionService->createExpense(
                $user,
                $account,
                $category,
                -$amountMinor,
                $occurredAt,
                $description,
            );
        }
    }

    private function looksLikeExpenseSignError(string $message): bool
    {
        $normalized = strtolower($message);

        return str_contains($normalized, 'less than zero')
            || str_contains($normalized, 'negative')
            || str_contains($normalized, 'must be less than');
    }

    private function categoryTypeColumnExists(): bool
    {
        if ($this->hasCategoryTypeColumn === null) {
            $this->hasCategoryTypeColumn = Schema::hasTable('categories')
                && Schema::hasColumn('categories', 'type');
        }

        return $this->hasCategoryTypeColumn;
    }

    private function categoryIsSystemColumnExists(): bool
    {
        if ($this->hasCategoryIsSystemColumn === null) {
            $this->hasCategoryIsSystemColumn = Schema::hasTable('categories')
                && Schema::hasColumn('categories', 'is_system');
        }

        return $this->hasCategoryIsSystemColumn;
    }

    /**
     * @param array<int, string|null> $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeLookupKey(string $value): string
    {
        $normalized = trim($value);

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($normalized);
        }

        return strtolower($normalized);
    }

    private function stripBom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }

    private function detectDelimiter(SplFileObject $csv): string
    {
        $csv->rewind();

        $firstLine = $csv->fgets();

        if (! is_string($firstLine) || $firstLine === '') {
            return ',';
        }

        $commaCount = substr_count($firstLine, ',');
        $semicolonCount = substr_count($firstLine, ';');

        return $semicolonCount > $commaCount ? ';' : ',';
    }
}
