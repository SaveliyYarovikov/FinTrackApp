<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    public function createIncome(
        User $user,
        Account $account,
        ?Category $category,
        int $amountMinor,
        string $occurredAt,
        ?string $description = null,
    ): Transaction {
        $this->assertPositiveAmount($amountMinor);

        return DB::transaction(function () use (
            $user,
            $account,
            $category,
            $amountMinor,
            $occurredAt,
            $description,
        ): Transaction {
            $lockedAccount = $this->lockOwnedAccounts($user, [$account->id])->first();
            $this->assertAccountNotArchived($lockedAccount);
            $resolvedCategory = $this->resolveCategory($user, $category);

            $entry = Transaction::query()->create([
                'user_id' => $user->id,
                'account_id' => $lockedAccount->id,
                'category_id' => $resolvedCategory?->id,
                'type' => Transaction::TYPE_INCOME,
                'amount' => $amountMinor,
                'description' => $description,
                'occurred_at' => Carbon::parse($occurredAt),
            ]);

            $this->applyDelta($lockedAccount, $entry->amount);

            return $entry->fresh(['account', 'category']);
        });
    }

    public function createExpense(
        User $user,
        Account $account,
        ?Category $category,
        int $amountMinor,
        string $occurredAt,
        ?string $description = null,
    ): Transaction {
        $this->assertNegativeAmount($amountMinor);

        return DB::transaction(function () use (
            $user,
            $account,
            $category,
            $amountMinor,
            $occurredAt,
            $description,
        ): Transaction {
            $lockedAccount = $this->lockOwnedAccounts($user, [$account->id])->first();
            $this->assertAccountNotArchived($lockedAccount);
            $resolvedCategory = $this->resolveCategory($user, $category);

            $entry = Transaction::query()->create([
                'user_id' => $user->id,
                'account_id' => $lockedAccount->id,
                'category_id' => $resolvedCategory?->id,
                'type' => Transaction::TYPE_EXPENSE,
                'amount' => $amountMinor,
                'description' => $description,
                'occurred_at' => Carbon::parse($occurredAt),
            ]);

            $this->applyDelta($lockedAccount, $entry->amount);

            return $entry->fresh(['account', 'category']);
        });
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function updateEntry(User $user, Transaction $entry, array $attributes): Transaction
    {
        return DB::transaction(function () use ($user, $entry, $attributes): Transaction {
            $lockedEntry = Transaction::query()
                ->lockForUpdate()
                ->findOrFail($entry->id);

            if ($lockedEntry->user_id !== $user->id) {
                throw new DomainException('You cannot update another user\'s entry.');
            }

            $newType = (string) ($attributes['type'] ?? $lockedEntry->type);

            if (! in_array($newType, [Transaction::TYPE_INCOME, Transaction::TYPE_EXPENSE, Transaction::TYPE_ADJUSTMENT], true)) {
                throw new DomainException('Unsupported entry type.');
            }

            $newAmount = array_key_exists('amount', $attributes)
                ? (int) $attributes['amount']
                : $lockedEntry->amount;

            if ($newType === Transaction::TYPE_INCOME && $newAmount <= 0) {
                throw new DomainException('Income amount must be greater than zero.');
            }

            if ($newType === Transaction::TYPE_EXPENSE && $newAmount >= 0) {
                throw new DomainException('Expense amount must be less than zero.');
            }

            if ($newType === Transaction::TYPE_ADJUSTMENT && $newAmount === 0) {
                throw new DomainException('Adjustment amount cannot be zero.');
            }

            $newAccountId = (int) ($attributes['account_id'] ?? $lockedEntry->account_id);
            $accounts = $this->lockOwnedAccounts($user, [$lockedEntry->account_id, $newAccountId]);
            $oldAccount = $accounts->get($lockedEntry->account_id);
            $newAccount = $accounts->get($newAccountId);

            if ($oldAccount === null || $newAccount === null) {
                throw new DomainException('Account must belong to the authenticated user.');
            }

            $this->assertAccountNotArchived($newAccount);

            $categoryId = array_key_exists('category_id', $attributes)
                ? $attributes['category_id']
                : $lockedEntry->category_id;

            $resolvedCategory = null;
            if ($categoryId !== null) {
                $resolvedCategory = Category::query()->find((int) $categoryId);
                $this->resolveCategory($user, $resolvedCategory);
            }

            if ($oldAccount->id === $newAccount->id) {
                $this->applyDelta($oldAccount, $newAmount - $lockedEntry->amount);
            } else {
                $this->applyDelta($oldAccount, -$lockedEntry->amount);
                $this->applyDelta($newAccount, $newAmount);
            }

            $lockedEntry->fill([
                'account_id' => $newAccount->id,
                'category_id' => $resolvedCategory?->id,
                'type' => $newType,
                'amount' => $newAmount,
                'description' => $attributes['description'] ?? $lockedEntry->description,
                'occurred_at' => isset($attributes['occurred_at'])
                    ? Carbon::parse((string) $attributes['occurred_at'])
                    : $lockedEntry->occurred_at,
            ]);

            $lockedEntry->save();

            return $lockedEntry->fresh(['account', 'category']);
        });
    }

    public function deleteEntry(User $user, Transaction $entry): void
    {
        DB::transaction(function () use ($user, $entry): void {
            $lockedEntry = Transaction::query()
                ->lockForUpdate()
                ->findOrFail($entry->id);

            if ($lockedEntry->user_id !== $user->id) {
                throw new DomainException('You cannot delete another user\'s entry.');
            }
            $lockedAccount = $this->lockOwnedAccounts($user, [$lockedEntry->account_id])->first();

            $this->applyDelta($lockedAccount, -$lockedEntry->amount);
            $lockedEntry->delete();
        });
    }

    /**
     * @param array<int, int> $accountIds
     * @return Collection<int, Account>
     */
    private function lockOwnedAccounts(User $user, array $accountIds): Collection
    {
        $ids = array_values(array_unique(array_map(static fn (int $id): int => $id, $accountIds)));

        if ($ids === []) {
            return collect();
        }

        $accounts = Account::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($accounts->count() !== count($ids)) {
            throw new DomainException('Account must belong to the authenticated user.');
        }

        return $accounts;
    }

    private function resolveCategory(User $user, ?Category $category): ?Category
    {
        if ($category === null) {
            return null;
        }

        $resolvedCategory = Category::query()->find($category->id);

        if ($resolvedCategory === null) {
            throw new DomainException('Category not found.');
        }

        if ($resolvedCategory->user_id !== $user->id) {
            throw new DomainException('Category must belong to the authenticated user.');
        }

        return $resolvedCategory;
    }

    private function assertAccountNotArchived(Account $account): void
    {
        if ($account->isArchived()) {
            throw new DomainException('Archived account cannot be used for new operations.');
        }
    }

    private function assertPositiveAmount(int $amountMinor): void
    {
        if ($amountMinor <= 0) {
            throw new DomainException('Amount must be greater than zero.');
        }
    }

    private function assertNegativeAmount(int $amountMinor): void
    {
        if ($amountMinor >= 0) {
            throw new DomainException('Amount must be less than zero.');
        }
    }

    private function applyDelta(Account $account, int $delta): void
    {
        if ($delta > 0) {
            $account->increment('balance', $delta);
            return;
        }

        if ($delta < 0) {
            $account->decrement('balance', abs($delta));
        }
    }
}
