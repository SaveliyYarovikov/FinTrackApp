<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringOperation;
use App\Models\Transaction;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RecurringOperationService
{
    private static ?bool $categoryTypeColumnExists = null;

    public function __construct(private readonly TransactionService $transactionService)
    {
    }

    /**
     * @return Transaction|array<int, Transaction>
     */
    public function apply(User $user, RecurringOperation $operation, string $occurredAt): Transaction|array
    {
        if ($operation->user_id !== $user->id) {
            throw new DomainException('You cannot apply another user\'s recurring operation.');
        }

        $amountMinor = (int) $operation->amount_minor;

        if ($amountMinor <= 0) {
            throw new DomainException('Recurring operation amount must be greater than zero.');
        }

        return match ($operation->type) {
            RecurringOperation::TYPE_INCOME => $this->applyIncome($user, $operation, $occurredAt, $amountMinor),
            RecurringOperation::TYPE_EXPENSE => $this->applyExpense($user, $operation, $occurredAt, $amountMinor),
            RecurringOperation::TYPE_TRANSFER => $this->applyTransfer($user, $operation, $occurredAt, $amountMinor),
            default => throw new DomainException('Unsupported recurring operation type.'),
        };
    }

    private function applyIncome(User $user, RecurringOperation $operation, string $occurredAt, int $amountMinor): Transaction
    {
        $this->assertIncomeOrExpenseStructure($operation);

        $account = $this->resolveOwnedActiveAccount($user, (int) $operation->account_id);
        $category = $this->resolveCategory($user, $operation->category_id, RecurringOperation::TYPE_INCOME);

        return $this->transactionService->createIncome(
            $user,
            $account,
            $category,
            $amountMinor,
            $occurredAt,
            $operation->description,
        );
    }

    private function applyExpense(User $user, RecurringOperation $operation, string $occurredAt, int $amountMinor): Transaction
    {
        $this->assertIncomeOrExpenseStructure($operation);

        $account = $this->resolveOwnedActiveAccount($user, (int) $operation->account_id);
        $category = $this->resolveCategory($user, $operation->category_id, RecurringOperation::TYPE_EXPENSE);

        return $this->transactionService->createExpense(
            $user,
            $account,
            $category,
            -$amountMinor,
            $occurredAt,
            $operation->description,
        );
    }

    /**
     * @return array<int, Transaction>
     */
    private function applyTransfer(User $user, RecurringOperation $operation, string $occurredAt, int $amountMinor): array
    {
        $this->assertTransferStructure($operation);

        $fromAccount = $this->resolveOwnedActiveAccount($user, (int) $operation->from_account_id);
        $toAccount = $this->resolveOwnedActiveAccount($user, (int) $operation->to_account_id);

        if ($fromAccount->id === $toAccount->id) {
            throw new DomainException('Source and destination accounts must differ.');
        }

        return DB::transaction(function () use (
            $user,
            $operation,
            $occurredAt,
            $amountMinor,
            $fromAccount,
            $toAccount,
        ): array {
            $transferId = (string) Str::uuid();

            $outgoing = $this->transactionService->createExpense(
                $user,
                $fromAccount,
                null,
                -$amountMinor,
                $occurredAt,
                $operation->description,
            );

            $incoming = $this->transactionService->createIncome(
                $user,
                $toAccount,
                null,
                $amountMinor,
                $occurredAt,
                $operation->description,
            );

            Transaction::query()->whereKey($outgoing->id)->update([
                'type' => Transaction::TYPE_TRANSFER,
                'category_id' => null,
                'transfer_id' => $transferId,
            ]);

            Transaction::query()->whereKey($incoming->id)->update([
                'type' => Transaction::TYPE_TRANSFER,
                'category_id' => null,
                'transfer_id' => $transferId,
            ]);

            return [
                Transaction::query()->with(['account', 'category'])->findOrFail($outgoing->id),
                Transaction::query()->with(['account', 'category'])->findOrFail($incoming->id),
            ];
        });
    }

    private function assertIncomeOrExpenseStructure(RecurringOperation $operation): void
    {
        if ($operation->account_id === null) {
            throw new DomainException('Account is required for income and expense operations.');
        }

        if ($operation->from_account_id !== null || $operation->to_account_id !== null) {
            throw new DomainException('Transfer accounts are not allowed for income and expense operations.');
        }
    }

    private function assertTransferStructure(RecurringOperation $operation): void
    {
        if ($operation->from_account_id === null || $operation->to_account_id === null) {
            throw new DomainException('Both transfer accounts are required.');
        }

        if ($operation->from_account_id === $operation->to_account_id) {
            throw new DomainException('Source and destination accounts must differ.');
        }

        if ($operation->account_id !== null || $operation->category_id !== null) {
            throw new DomainException('Transfer operations cannot have account_id or category_id.');
        }
    }

    private function resolveOwnedActiveAccount(User $user, int $accountId): Account
    {
        $account = Account::query()
            ->where('user_id', $user->id)
            ->whereKey($accountId)
            ->first();

        if ($account === null) {
            throw new DomainException('Account must belong to the authenticated user.');
        }

        if ($account->isArchived()) {
            throw new DomainException('Archived account cannot be used for new operations.');
        }

        return $account;
    }

    private function resolveCategory(User $user, ?int $categoryId, string $type): ?Category
    {
        if ($categoryId === null) {
            return null;
        }

        $category = Category::query()
            ->forUser($user->id)
            ->whereKey($categoryId)
            ->first();

        if ($category === null) {
            throw new DomainException('Category must belong to the authenticated user.');
        }

        if ($this->categoryTypeColumnExists() && $category->type !== null && $category->type !== $type) {
            throw new DomainException('Category type must match operation type.');
        }

        return $category;
    }

    private function categoryTypeColumnExists(): bool
    {
        if (self::$categoryTypeColumnExists === null) {
            self::$categoryTypeColumnExists = Schema::hasColumn('categories', 'type');
        }

        return self::$categoryTypeColumnExists;
    }
}
