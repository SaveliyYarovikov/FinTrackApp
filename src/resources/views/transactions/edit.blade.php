<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Edit Transaction</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('transactions.update', $entry) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="text-sm text-gray-600 dark:text-gray-300">
                        <div><strong>Type:</strong> {{ ucfirst($entry->type) }}</div>
                        <div><strong>Account:</strong> {{ $entry->account->name }} ({{ $entry->account->currency }})</div>
                        <div><strong>Date:</strong> {{ $entry->occurred_at->format('Y-m-d H:i') }}</div>
                    </div>

                    <div>
                        <x-input-label for="category_id" value="Category" />
                        <select id="category_id" name="category_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                            <option value="">No category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) old('category_id', $entry->category_id) === (string) $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="amount" value="Amount" />
                        <x-text-input
                            id="amount"
                            name="amount"
                            type="text"
                            class="mt-1 block w-full"
                            :value="old('amount', number_format($entry->amount_minor / 100, 2, '.', ''))"
                            required
                        />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" value="Description" />
                        <x-text-input id="description" name="description" type="text" class="mt-1 block w-full" :value="old('description', $entry->description)" />
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <x-input-error :messages="$errors->get('entry')" class="mt-2" />

                    <div class="flex justify-end gap-2">
                        <a href="{{ route('transactions.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-xs uppercase">Cancel</a>
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
