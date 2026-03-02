<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Categories</h2>
            <a href="{{ route('categories.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                New Category
            </a>
        </div>
    </x-slot>

    <div
        class="py-10"
        x-data="{
            selectedCategoryIds: [],
            manualSelectionUnlocked: false,
            pageCategoryIds: @js($pageCategoryIds),
            get allPageCategoriesSelected() {
                return this.pageCategoryIds.length > 0
                    && this.pageCategoryIds.every((categoryId) => this.selectedCategoryIds.includes(categoryId));
            },
            togglePageSelection(checked) {
                if (! this.manualSelectionUnlocked) {
                    return;
                }

                this.selectedCategoryIds = checked ? [...this.pageCategoryIds] : [];
            },
            isCategorySelected(categoryId) {
                return this.selectedCategoryIds.includes(String(categoryId));
            },
        }"
    >
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                @if (session('status'))
                    <div class="mb-4 text-sm text-green-600 dark:text-green-400">{{ session('status') }}</div>
                @endif

                <x-input-error :messages="$errors->get('category_ids')" class="mb-4" />

                <h3 class="font-semibold mb-2 text-gray-900 dark:text-gray-100">Your categories</h3>

                <form
                    method="POST"
                    action="{{ route('categories.bulk-destroy') }}"
                    x-show="selectedCategoryIds.length > 0"
                    x-cloak
                    @submit="if (!confirm('Delete selected categories?')) { $event.preventDefault(); }"
                    class="mb-4 flex flex-wrap items-center gap-2"
                >
                    @csrf
                    @method('DELETE')
                    <template x-for="id in selectedCategoryIds" :key="id">
                        <input type="hidden" name="category_ids[]" :value="id">
                    </template>

                    <span class="text-sm text-gray-600 dark:text-gray-300" x-text="`${selectedCategoryIds.length} selected`"></span>
                    <button type="submit" class="inline-flex items-center px-3 py-2 bg-red-600 rounded-md text-xs font-semibold text-white uppercase">
                        Delete Selected
                    </button>
                </form>

                <div class="overflow-x-auto mb-6">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <th class="py-2 pr-4 pl-2 w-10">
                                    <input
                                        type="checkbox"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 disabled:opacity-50"
                                        :disabled="!manualSelectionUnlocked || pageCategoryIds.length === 0"
                                        :checked="allPageCategoriesSelected"
                                        @change="togglePageSelection($event.target.checked)"
                                        title="Select all on page"
                                    />
                                </th>
                                <th class="py-2 pr-4">Name</th>
                                <th class="py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-sm text-gray-900 dark:text-gray-100">
                        @forelse ($userCategories as $category)
                            <tr
                                :class="isCategorySelected({{ $category->id }}) ? 'bg-slate-800 text-gray-100' : ''"
                                class="transition-colors"
                            >
                                <td class="py-3 pr-4 pl-2">
                                    <input
                                        type="checkbox"
                                        value="{{ $category->id }}"
                                        x-model="selectedCategoryIds"
                                        @change="
                                            if ($event.target.checked) {
                                                manualSelectionUnlocked = true;
                                            } else {
                                                $nextTick(() => {
                                                    if (selectedCategoryIds.length === 0) {
                                                        $event.target.blur();
                                                    }
                                                });
                                            }
                                        "
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                    />
                                </td>
                                <td class="py-3 pr-4">{{ $category->name }}</td>
                                <td class="py-3">
                                    <div class="flex gap-2 text-xs uppercase">
                                        <a href="{{ route('categories.edit', $category) }}" class="text-indigo-600 hover:text-indigo-500">Edit</a>
                                        <form method="POST" action="{{ route('categories.destroy', $category) }}" onsubmit="return confirm('Delete category?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-500">DELETE</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-4 text-sm text-gray-500 dark:text-gray-400">No personal categories.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
