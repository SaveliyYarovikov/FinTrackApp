<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Budgets\StoreBudgetRequest;
use App\Http\Requests\Budgets\UpdateBudgetRequest;
use App\Models\Budget;
use App\Models\Category;
use App\Services\BudgetService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function __construct(private readonly BudgetService $budgetService)
    {
    }

    public function index(Request $request): View
    {
        return view('budgets.index', [
            'rows' => $this->budgetService->buildBudgetRows($request->user()),
        ]);
    }

    public function create(Request $request): View
    {
        return view('budgets.create', [
            'selectedCategoryId' => $request->query('category_id'),
            'categories' => $this->availableCategories($request),
        ]);
    }

    public function store(StoreBudgetRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Budget::query()->create([
            'user_id' => $request->user()->id,
            'category_id' => (int) $validated['category_id'],
            'period_start' => (string) $validated['period_start'],
            'period_end' => (string) $validated['period_end'],
            'limit_minor' => Money::parseMajorToMinor((string) $validated['amount']),
        ]);

        return redirect()
            ->route('budgets.index')
            ->with('status', 'Budget created.');
    }

    public function edit(Request $request, Budget $budget): View
    {
        abort_unless($budget->user_id === $request->user()->id, 403);

        return view('budgets.edit', [
            'budget' => $budget,
            'categories' => $this->availableCategories($request),
        ]);
    }

    public function update(UpdateBudgetRequest $request, Budget $budget): RedirectResponse
    {
        abort_unless($budget->user_id === $request->user()->id, 403);

        $validated = $request->validated();

        $budget->update([
            'category_id' => (int) $validated['category_id'],
            'period_start' => (string) $validated['period_start'],
            'period_end' => (string) $validated['period_end'],
            'limit_minor' => Money::parseMajorToMinor((string) $validated['amount']),
        ]);

        return redirect()
            ->route('budgets.index')
            ->with('status', 'Budget updated.');
    }

    public function destroy(Request $request, Budget $budget): RedirectResponse
    {
        abort_unless($budget->user_id === $request->user()->id, 403);

        $budget->delete();

        return redirect()
            ->route('budgets.index')
            ->with('status', 'Budget deleted.');
    }

    /**
     * @return Collection<int, Category>
     */
    private function availableCategories(Request $request): Collection
    {
        return Category::query()
            ->forUser($request->user()->id)
            ->orderBy('name')
            ->get();
    }
}
