<?php

declare(strict_types=1);


namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'account_id' => ['nullable', 'integer'],
        ]);

        $userId = $request->user()->id;

        $startDate = isset($validated['start_date'])
            ? Carbon::parse((string) $validated['start_date'])->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $endDate = isset($validated['end_date'])
            ? Carbon::parse((string) $validated['end_date'])->endOfDay()
            : now()->endOfDay();

        $accounts = Account::query()
            ->where('user_id', $userId)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get();

        $currencyTotals = $accounts
            ->groupBy('currency')
            ->map(static fn (Collection $accountsByCurrency): int => (int) $accountsByCurrency->sum('balance'))
            ->sortKeys();

        $selectedAccountId = isset($validated['account_id'])
            ? (int) $validated['account_id']
            : null;

        $selectedAccount = null;
        if ($selectedAccountId !== null) {
            $selectedAccount = Account::query()
                ->where('user_id', $userId)
                ->whereNull('archived_at')
                ->whereKey($selectedAccountId)
                ->firstOrFail();
        }

        $baseTransactionsQuery = Transaction::query()
            ->where('user_id', $userId)
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if ($selectedAccount !== null) {
            $baseTransactionsQuery->where('account_id', $selectedAccount->id);
        }

        $totalIncomeMinor = (int) (clone $baseTransactionsQuery)
            ->where('type', Transaction::TYPE_INCOME)
            ->sum('amount');

        $totalExpenseMinorAbs = abs((int) (clone $baseTransactionsQuery)
            ->where('type', Transaction::TYPE_EXPENSE)
            ->sum('amount'));

        $expensesGroupedByCategory = (clone $baseTransactionsQuery)
            ->where('type', Transaction::TYPE_EXPENSE)
            ->selectRaw('category_id, SUM(amount) as total_minor')
            ->groupBy('category_id')
            ->get()
            ->map(static function (object $row): array {
                return [
                    'category_id' => $row->category_id !== null ? (int) $row->category_id : null,
                    'total_minor' => abs((int) $row->total_minor),
                ];
            })
            ->filter(static fn (array $row): bool => $row['total_minor'] > 0)
            ->sortByDesc('total_minor')
            ->values();

        $categoryIds = $expensesGroupedByCategory
            ->pluck('category_id')
            ->filter(static fn (mixed $categoryId): bool => $categoryId !== null)
            ->map(static fn (mixed $categoryId): int => (int) $categoryId)
            ->values()
            ->all();

        $categoryNamesById = Category::query()
            ->forUser($userId)
            ->whereIn('id', $categoryIds)
            ->pluck('name', 'id')
            ->all();

        $expensesByLabel = [];
        foreach ($expensesGroupedByCategory as $expenseRow) {
            $categoryId = $expenseRow['category_id'];
            $label = $categoryId === null
                ? 'Uncategorized'
                : ($categoryNamesById[$categoryId] ?? 'Uncategorized');

            if (! array_key_exists($label, $expensesByLabel)) {
                $expensesByLabel[$label] = 0;
            }

            $expensesByLabel[$label] += $expenseRow['total_minor'];
        }

        arsort($expensesByLabel);

        $chartLabels = array_keys($expensesByLabel);
        $chartValuesMinor = array_values($expensesByLabel);
        $chartValuesMajor = array_map(
            static fn (int $amountMinor): float => round($amountMinor / 100, 2),
            $chartValuesMinor,
        );

        $uniqueCurrencies = $accounts
            ->pluck('currency')
            ->filter(static fn (mixed $currency): bool => is_string($currency) && $currency !== '')
            ->unique()
            ->values();

        $totalsCurrency = $selectedAccount?->currency;
        if ($totalsCurrency === null && $uniqueCurrencies->count() === 1) {
            $totalsCurrency = (string) $uniqueCurrencies->first();
        }

        return view('dashboard', [
            'accounts' => $accounts,
            'currencyTotals' => $currencyTotals,
            'startDate' => $startDate->toDateString(),
            'endDate' => $endDate->toDateString(),
            'selectedAccountId' => $selectedAccountId,
            'selectedAccount' => $selectedAccount,
            'totalIncomeMinor' => $totalIncomeMinor,
            'totalExpenseMinorAbs' => $totalExpenseMinorAbs,
            'totalsCurrency' => $totalsCurrency,
            'chartLabels' => $chartLabels,
            'chartValuesMajor' => $chartValuesMajor,
        ]);
    }
}
