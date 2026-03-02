<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;

class BudgetService
{
    /**
     * @return array{Carbon, Carbon}
     */
    public function getDateRange(Carbon $periodStart, Carbon $periodEnd): array
    {
        $start = $periodStart->copy()->startOfDay();
        $end = $periodEnd->copy()->addDay()->startOfDay();

        return [$start, $end];
    }

    public function calculateSpentMinor(User $user, Category $category, Carbon $periodStart, Carbon $periodEnd): int
    {
        [$start, $end] = $this->getDateRange($periodStart, $periodEnd);

        $sum = (int) Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', Transaction::TYPE_EXPENSE)
            ->where('category_id', $category->id)
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end)
            ->sum('amount_minor');

        return abs($sum);
    }

    /**
     * @return array<int, array{
     *     category: Category,
     *     budget: Budget,
     *     limit_minor: int,
     *     spent_minor: int,
     *     remaining_minor: int,
     *     progress_percent: float
     * }>
     */
    public function buildBudgetRows(User $user): array
    {
        $budgets = Budget::query()
            ->forUser($user->id)
            ->whereHas('category')
            ->with('category')
            ->orderByDesc('period_start')
            ->orderByDesc('period_end')
            ->orderBy('id')
            ->get();

        if ($budgets->isEmpty()) {
            return [];
        }

        return $budgets
            ->map(function (Budget $budget) use ($user): array {
                /** @var Category $category */
                $category = $budget->category;
                $limitMinor = (int) $budget->limit_minor;
                $spentMinor = $this->calculateSpentMinor(
                    $user,
                    $category,
                    $budget->period_start,
                    $budget->period_end,
                );
                $remainingMinor = $limitMinor - $spentMinor;
                $progressPercent = $limitMinor > 0
                    ? ($spentMinor / $limitMinor) * 100
                    : 0.0;

                return [
                    'category' => $category,
                    'budget' => $budget,
                    'limit_minor' => $limitMinor,
                    'spent_minor' => $spentMinor,
                    'remaining_minor' => $remainingMinor,
                    'progress_percent' => $progressPercent,
                ];
            })
            ->values()
            ->all();
    }
}
