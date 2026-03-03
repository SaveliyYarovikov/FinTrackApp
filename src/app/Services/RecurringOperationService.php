<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringOperation;
use App\Models\Transaction;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Schema;

class RecurringOperationService
{
    private static ?bool $categoryTypeColumnExists = null;

    public function __construct(private readonly TransactionService $transactionService)
    {
    }

    public function apply(User $user, RecurringOperation $operation, string $occurredAt): Transaction
    {
        if ($operation->user_id !== $user->id) {
            throw new DomainException('You cannot apply another user\'s recurring operation.');
        }

        $amountMinor = (int) $operation->amount;

        if ($amountMinor <= 0) {
            throw new DomainException('Recurring operation amount must be greater than zero.');
        }

        return match ($operation->type) {
            RecurringOperation::TYPE_INCOME => $this->applyIncome($user, $operation, $occurredAt, $amountMinor),
            RecurringOperation::TYPE_EXPENSE => $this->applyExpense($user, $operation, $occurredAt, $amountMinor),
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
        );
    }

    private function assertIncomeOrExpenseStructure(RecurringOperation $operation): void
    {
        if ($operation->account_id === null) {
            throw new DomainException('Account is required for income and expense operations.');
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
