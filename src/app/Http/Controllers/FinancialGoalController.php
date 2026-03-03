<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\FinancialGoals\StoreFinancialGoalRequest;
use App\Http\Requests\FinancialGoals\UpdateFinancialGoalRequest;
use App\Models\Account;
use App\Models\FinancialGoal;
use App\Services\FinancialGoalService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialGoalController extends Controller
{
    public function __construct(private readonly FinancialGoalService $financialGoalService)
    {
    }

    public function index(Request $request): View
    {
        $includeArchived = $request->boolean('archived');

        return view('financial-goals.index', [
            'rows' => $this->financialGoalService->buildGoalViewModels(
                $request->user(),
                $includeArchived,
            ),
            'includeArchived' => $includeArchived,
        ]);
    }

    public function create(Request $request): View
    {
        return view('financial-goals.create', [
            'accounts' => $this->availableSavingsAccounts($request),
        ]);
    }

    public function store(StoreFinancialGoalRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $account = Account::query()
            ->where('user_id', $request->user()->id)
            ->where('type', Account::TYPE_SAVINGS)
            ->whereNull('archived_at')
            ->whereKey((int) $validated['account_id'])
            ->firstOrFail();

        FinancialGoal::query()->create([
            'user_id' => $request->user()->id,
            'account_id' => $account->id,
            'name' => (string) $validated['name'],
            'description' => $validated['description'] ?? null,
            'target_amount' => Money::parseMajorToMinor((string) $validated['amount']),
            'target_date' => $validated['target_date'] ?? null,
            'status' => FinancialGoal::STATUS_ACTIVE,
        ]);

        return redirect()
            ->route('financial-goals.index')
            ->with('status', 'Financial goal created.');
    }

    public function edit(Request $request, FinancialGoal $financialGoal): View
    {
        $this->ensureOwnership($request, $financialGoal);

        return view('financial-goals.edit', [
            'goal' => $financialGoal->load('account'),
        ]);
    }

    public function update(UpdateFinancialGoalRequest $request, FinancialGoal $financialGoal): RedirectResponse
    {
        $this->ensureOwnership($request, $financialGoal);

        $validated = $request->validated();

        $financialGoal->update([
            'name' => (string) $validated['name'],
            'description' => $validated['description'] ?? null,
            'target_amount' => Money::parseMajorToMinor((string) $validated['amount']),
            'target_date' => $validated['target_date'] ?? null,
            'status' => $validated['status'] ?? $financialGoal->status,
        ]);

        return redirect()
            ->route('financial-goals.index')
            ->with('status', 'Financial goal updated.');
    }

    public function destroy(Request $request, FinancialGoal $financialGoal): RedirectResponse
    {
        $this->ensureOwnership($request, $financialGoal);

        $financialGoal->delete();

        return redirect()
            ->route('financial-goals.index')
            ->with('status', 'Financial goal deleted.');
    }

    public function archive(Request $request, FinancialGoal $financialGoal): RedirectResponse
    {
        $this->ensureOwnership($request, $financialGoal);

        if ($financialGoal->status !== FinancialGoal::STATUS_ARCHIVED) {
            $financialGoal->status = FinancialGoal::STATUS_ARCHIVED;
            $financialGoal->save();
        }

        return redirect()
            ->route('financial-goals.index')
            ->with('status', 'Financial goal archived.');
    }

    /**
     * @return Collection<int, Account>
     */
    private function availableSavingsAccounts(Request $request): Collection
    {
        return Account::query()
            ->where('user_id', $request->user()->id)
            ->where('type', Account::TYPE_SAVINGS)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get();
    }

    private function ensureOwnership(Request $request, FinancialGoal $financialGoal): void
    {
        abort_unless($financialGoal->user_id === $request->user()->id, 403);
    }
}
