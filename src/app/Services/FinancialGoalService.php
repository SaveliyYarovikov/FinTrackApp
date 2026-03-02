<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\FinancialGoal;
use App\Models\User;
use Illuminate\Support\Collection;

class FinancialGoalService
{
    /**
     * @return Collection<int, array{
     *     goal: FinancialGoal,
     *     account: Account,
     *     currency: string,
     *     current_balance_minor: int,
     *     saved_minor: int,
     *     remaining_minor: int,
     *     progress_percent: int,
     *     is_overdue: bool
     * }>
     */
    public function buildGoalViewModels(User $user, bool $includeArchived = false): Collection
    {
        $query = FinancialGoal::query()
            ->forUser($user->id)
            ->whereHas('account')
            ->with('account')
            ->orderBy('target_date')
            ->orderBy('id');

        if (! $includeArchived) {
            $query->where('status', '!=', FinancialGoal::STATUS_ARCHIVED);
        }

        /** @var Collection<int, FinancialGoal> $goals */
        $goals = $query->get();
        $today = now()->startOfDay();

        return $goals->map(function (FinancialGoal $goal) use ($today): array {
            /** @var Account $account */
            $account = $goal->account;
            $progress = $this->calculateProgress($goal, $account);
            $this->syncStatusByProgress($goal, $progress['remaining_minor']);

            $isOverdue = $goal->target_date !== null
                && $progress['remaining_minor'] > 0
                && $today->greaterThan($goal->target_date->copy()->startOfDay());

            return [
                'goal' => $goal,
                'account' => $account,
                'currency' => $account->currency,
                'current_balance_minor' => $progress['current_balance_minor'],
                'saved_minor' => $progress['saved_minor'],
                'remaining_minor' => $progress['remaining_minor'],
                'progress_percent' => $progress['progress_percent'],
                'is_overdue' => $isOverdue,
            ];
        });
    }

    /**
     * @return array{
     *     current_balance_minor: int,
     *     saved_minor: int,
     *     remaining_minor: int,
     *     progress_percent: int
     * }
     */
    public function calculateProgress(FinancialGoal $goal, Account $account): array
    {
        $currentBalanceMinor = (int) $account->balance_minor;
        $startBalanceMinor = (int) $goal->start_balance_minor;
        $targetAmountMinor = (int) $goal->target_amount_minor;
        $savedMinor = max(0, $currentBalanceMinor - $startBalanceMinor);
        $remainingMinor = $targetAmountMinor - $savedMinor;
        $progressPercent = $targetAmountMinor > 0
            ? (int) min(100, round(($savedMinor / $targetAmountMinor) * 100))
            : 0;

        return [
            'current_balance_minor' => $currentBalanceMinor,
            'saved_minor' => $savedMinor,
            'remaining_minor' => $remainingMinor,
            'progress_percent' => $progressPercent,
        ];
    }

    private function syncStatusByProgress(FinancialGoal $goal, int $remainingMinor): void
    {
        if ($goal->status === FinancialGoal::STATUS_ARCHIVED) {
            return;
        }

        $resolvedStatus = $remainingMinor <= 0
            ? FinancialGoal::STATUS_ACHIEVED
            : FinancialGoal::STATUS_ACTIVE;

        if ($goal->status === $resolvedStatus) {
            return;
        }

        $goal->status = $resolvedStatus;
        $goal->save();
    }
}
