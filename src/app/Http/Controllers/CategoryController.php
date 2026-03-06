<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $userCategories = Category::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get();

        return view('categories.index', [
            'userCategories' => $userCategories,
            'pageCategoryIds' => $userCategories
                ->pluck('id')
                ->map(static fn ($id): string => (string) $id)
                ->values()
                ->all(),
        ]);
    }

    public function create(): View
    {
        return view('categories.create');
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Category::query()->create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
        ]);

        return redirect()
            ->route('categories.index')
            ->with('status', 'Category created successfully.');
    }

    public function edit(Request $request, Category $category): View
    {
        $this->ensureOwnership($request, $category);

        return view('categories.edit', [
            'category' => $category,
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->ensureOwnership($request, $category);

        $category->update($request->validated());

        return redirect()
            ->route('categories.index')
            ->with('status', 'Category updated successfully.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $categoryIds = array_values(array_unique(array_map(
            static fn (mixed $categoryId): int => (int) $categoryId,
            $validated['category_ids'],
        )));

        $ownedCategoryIds = Category::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $categoryIds)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        sort($categoryIds);
        sort($ownedCategoryIds);

        if ($categoryIds !== $ownedCategoryIds) {
            return back()->withErrors(['category_ids' => 'One or more selected categories are invalid.']);
        }

        Category::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $categoryIds)
            ->delete();

        return redirect()
            ->route('categories.index')
            ->with('status', 'Selected categories deleted.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()
            ->route('categories.index')
            ->with('status', 'Category deleted successfully.');
    }

    private function ensureOwnership(Request $request, Category $category): void
    {
        abort_unless($category->user_id === $request->user()->id, 403);
    }
}
