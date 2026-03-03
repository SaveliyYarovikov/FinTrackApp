<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Edit Budget</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('budgets.update', $budget) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="period_start" value="Period start" />
                        <x-text-input id="period_start" name="period_start" type="date" class="mt-1 block w-full" :value="old('period_start', $budget->period_start->toDateString())" required />
                        <x-input-error :messages="$errors->get('period_start')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="period_end" value="Period end" />
                        <x-text-input id="period_end" name="period_end" type="date" class="mt-1 block w-full" :value="old('period_end', $budget->period_end->toDateString())" required />
                        <x-input-error :messages="$errors->get('period_end')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="category_id" value="Category" />
                        <select id="category_id" name="category_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm" required>
                            <option value="">Select category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) old('category_id', $budget->category_id) === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="amount" value="Limit" />
                        <x-text-input
                            id="amount"
                            name="amount"
                            type="text"
                            class="mt-1 block w-full"
                            :value="old('amount', number_format($budget->limit / 100, 2, '.', ''))"
                            required
                        />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>

                    <div class="flex justify-end gap-2">
                        <a href="{{ route('budgets.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-xs uppercase">Cancel</a>
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
