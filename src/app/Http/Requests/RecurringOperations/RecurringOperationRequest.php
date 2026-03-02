<?php

declare(strict_types=1);

namespace App\Http\Requests\RecurringOperations;

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringOperation;
use App\Support\Money;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

abstract class RecurringOperationRequest extends FormRequest
{
    private static ?bool $categoryTypeColumnExists = null;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => [
                'required',
                'string',
                Rule::in([
                    RecurringOperation::TYPE_INCOME,
                    RecurringOperation::TYPE_EXPENSE,
                    RecurringOperation::TYPE_TRANSFER,
                ]),
            ],
            'amount' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    try {
                        $amountMinor = Money::parseMajorToMinor((string) $value);
                    } catch (InvalidArgumentException) {
                        $fail('Amount format is invalid.');
                        return;
                    }

                    if ($amountMinor <= 0) {
                        $fail('Amount must be greater than zero.');
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'account_id' => [
                Rule::requiredIf(fn (): bool => $this->isIncomeOrExpense()),
                Rule::prohibitedIf(fn (): bool => $this->isTransfer()),
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $this->validateOwnedActiveAccount((int) $value, $fail, 'Selected account is invalid.');
                },
            ],
            'from_account_id' => [
                Rule::requiredIf(fn (): bool => $this->isTransfer()),
                Rule::prohibitedIf(fn (): bool => ! $this->isTransfer()),
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $this->validateOwnedActiveAccount((int) $value, $fail, 'Source account is invalid.');
                },
            ],
            'to_account_id' => [
                Rule::requiredIf(fn (): bool => $this->isTransfer()),
                Rule::prohibitedIf(fn (): bool => ! $this->isTransfer()),
                'nullable',
                'integer',
                'different:from_account_id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $this->validateOwnedActiveAccount((int) $value, $fail, 'Destination account is invalid.');
                },
            ],
            'category_id' => [
                Rule::prohibitedIf(fn (): bool => $this->isTransfer()),
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $category = Category::query()
                        ->forUser($this->user()->id)
                        ->whereKey((int) $value)
                        ->first();

                    if ($category === null) {
                        $fail('Selected category is invalid.');
                        return;
                    }

                    if ($this->categoryTypeColumnExists() && $category->type !== null && $category->type !== $this->normalizedType()) {
                        $fail('Category type must match operation type.');
                    }
                },
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $amount = $this->input('amount');
        $description = $this->input('description');

        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'type' => $this->normalizedType(),
            'amount' => is_string($amount) ? trim($amount) : $amount,
            'description' => is_string($description) ? trim($description) : $description,
            'account_id' => $this->normalizeNullableInt('account_id'),
            'from_account_id' => $this->normalizeNullableInt('from_account_id'),
            'to_account_id' => $this->normalizeNullableInt('to_account_id'),
            'category_id' => $this->normalizeNullableInt('category_id'),
        ]);
    }

    private function validateOwnedActiveAccount(int $accountId, Closure $fail, string $invalidMessage): void
    {
        $account = Account::query()
            ->where('user_id', $this->user()->id)
            ->whereKey($accountId)
            ->first();

        if ($account === null) {
            $fail($invalidMessage);
            return;
        }

        if ($account->isArchived()) {
            $fail('Archived account cannot be used.');
        }
    }

    private function normalizeNullableInt(string $key): mixed
    {
        $value = $this->input($key);

        return $value === '' ? null : $value;
    }

    private function normalizedType(): string
    {
        return strtolower(trim((string) $this->input('type', '')));
    }

    private function isTransfer(): bool
    {
        return $this->normalizedType() === RecurringOperation::TYPE_TRANSFER;
    }

    private function isIncomeOrExpense(): bool
    {
        return in_array(
            $this->normalizedType(),
            [RecurringOperation::TYPE_INCOME, RecurringOperation::TYPE_EXPENSE],
            true,
        );
    }

    private function categoryTypeColumnExists(): bool
    {
        if (self::$categoryTypeColumnExists === null) {
            self::$categoryTypeColumnExists = Schema::hasColumn('categories', 'type');
        }

        return self::$categoryTypeColumnExists;
    }
}
