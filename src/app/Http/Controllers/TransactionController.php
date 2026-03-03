<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Transactions\StoreExpenseRequest;
use App\Http\Requests\Transactions\StoreIncomeRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Models\Category;
use App\Models\RecurringOperation;
use App\Models\Transaction;
use App\Services\TransactionService;
use App\Support\Money;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function __construct(private readonly TransactionService $transactionService)
    {
    }

    public function index(Request $request): View
    {
        $query = Transaction::query()
            ->where('user_id', $request->user()->id)
            ->with(['account', 'category'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if ($request->filled('from')) {
            $query->whereDate('occurred_at', '>=', (string) $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('occurred_at', '<=', (string) $request->input('to'));
        }

        if ($request->filled('account_id')) {
            $query->where('account_id', (int) $request->input('account_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->input('category_id'));
        }

        if ($request->filled('type')) {
            $type = (string) $request->input('type');

            if (in_array($type, [Transaction::TYPE_INCOME, Transaction::TYPE_EXPENSE], true)) {
                $query->where('type', $type);
            }
        }

        $entries = $query->paginate(25)->withQueryString();

        $accounts = $request->user()
            ->accounts()
            ->orderBy('name')
            ->get();

        $categories = Category::query()
            ->forUser($request->user()->id)
            ->orderBy('name')
            ->get();

        $recurringOperations = RecurringOperation::query()
            ->forUser($request->user()->id)
            ->whereIn('type', [RecurringOperation::TYPE_INCOME, RecurringOperation::TYPE_EXPENSE])
            ->orderBy('name')
            ->get();

        return view('transactions.index', [
            'entries' => $entries,
            'accounts' => $accounts,
            'categories' => $categories,
            'recurringOperations' => $recurringOperations,
            'pageEntryIds' => $entries->getCollection()
                ->pluck('id')
                ->map(static fn ($id): string => (string) $id)
                ->values()
                ->all(),
        ]);
    }

    public function createIncome(Request $request): View
    {
        return view('transactions.create-income', [
            'accounts' => $request->user()->accounts()->whereNull('archived_at')->orderBy('name')->get(),
            'categories' => Category::query()
                ->forUser($request->user()->id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeIncome(StoreIncomeRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $account = $user->accounts()->findOrFail((int) $validated['account_id']);

        $category = null;
        if (! empty($validated['category_id'])) {
            $category = Category::query()
                ->forUser($user->id)
                ->whereKey((int) $validated['category_id'])
                ->firstOrFail();
        }

        try {
            $this->transactionService->createIncome(
                $user,
                $account,
                $category,
                Money::parseMajorToMinor((string) $validated['amount']),
                (string) $validated['occurred_at'],
                $validated['description'] ?? null,
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['amount' => $exception->getMessage()]);
        }

        return redirect()
            ->route('transactions.index')
            ->with('status', 'Income recorded.');
    }

    public function createExpense(Request $request): View
    {
        return view('transactions.create-expense', [
            'accounts' => $request->user()->accounts()->whereNull('archived_at')->orderBy('name')->get(),
            'categories' => Category::query()
                ->forUser($request->user()->id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeExpense(StoreExpenseRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $account = $user->accounts()->findOrFail((int) $validated['account_id']);

        $category = null;
        if (! empty($validated['category_id'])) {
            $category = Category::query()
                ->forUser($user->id)
                ->whereKey((int) $validated['category_id'])
                ->firstOrFail();
        }

        try {
            $this->transactionService->createExpense(
                $user,
                $account,
                $category,
                Money::parseMajorToMinor((string) $validated['amount']),
                (string) $validated['occurred_at'],
                $validated['description'] ?? null,
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['amount' => $exception->getMessage()]);
        }

        return redirect()
            ->route('transactions.index')
            ->with('status', 'Expense recorded.');
    }

    public function edit(Request $request, Transaction $entry): View
    {
        return view('transactions.edit', [
            'entry' => $entry->load(['account', 'category']),
            'categories' => Category::query()
                ->forUser($request->user()->id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(UpdateTransactionRequest $request, Transaction $entry): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $this->transactionService->updateEntry(
                $request->user(),
                $entry,
                [
                    'amount' => Money::parseMajorToMinor((string) $validated['amount']),
                    'description' => $validated['description'] ?? null,
                    'category_id' => $validated['category_id'] ?? null,
                ],
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['entry' => $exception->getMessage()]);
        }

        return redirect()
            ->route('transactions.index')
            ->with('status', 'Transaction updated.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'entry_ids' => ['required', 'array', 'min:1'],
            'entry_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $entryIds = array_values(array_unique(array_map(
            static fn (mixed $entryId): int => (int) $entryId,
            $validated['entry_ids'],
        )));

        $ownedEntryIds = Transaction::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $entryIds)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        sort($entryIds);
        sort($ownedEntryIds);

        if ($entryIds !== $ownedEntryIds) {
            return back()->withErrors(['entry_ids' => 'One or more selected entries are invalid.']);
        }

        foreach ($entryIds as $entryId) {
            $entry = Transaction::query()
                ->where('user_id', $request->user()->id)
                ->find($entryId);

            if ($entry === null) {
                continue;
            }

            try {
                $this->transactionService->deleteEntry($request->user(), $entry);
            } catch (DomainException $exception) {
                return back()->withErrors(['entry_ids' => $exception->getMessage()]);
            }
        }

        return redirect()
            ->route('transactions.index')
            ->with('status', 'Selected entries deleted.');
    }

    public function destroy(Request $request, Transaction $entry): RedirectResponse
    {
        try {
            $this->transactionService->deleteEntry($request->user(), $entry);
        } catch (DomainException $exception) {
            return back()->withErrors(['entry' => $exception->getMessage()]);
        }

        return redirect()
            ->route('transactions.index')
            ->with('status', 'Entry deleted.');
    }
}
