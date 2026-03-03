@php
    $selectedType = old('type', $operation->type ?? \App\Models\RecurringOperation::TYPE_EXPENSE);
    if (! in_array($selectedType, [\App\Models\RecurringOperation::TYPE_INCOME, \App\Models\RecurringOperation::TYPE_EXPENSE], true)) {
        $selectedType = \App\Models\RecurringOperation::TYPE_EXPENSE;
    }
    $defaultAmount = old('amount', isset($operation) ? number_format($operation->amount / 100, 2, '.', '') : '');
@endphp

<form method="POST" action="{{ $action }}" class="space-y-4">
    @csrf
    @if (($method ?? 'POST') !== 'POST')
        @method($method)
    @endif

    <div>
        <x-input-label for="name" value="Name" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $operation->name ?? '')" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="type" value="Type" />
        <select
            id="type"
            name="type"
            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm"
            required
        >
            <option value="{{ \App\Models\RecurringOperation::TYPE_INCOME }}" @selected($selectedType === \App\Models\RecurringOperation::TYPE_INCOME)>Income</option>
            <option value="{{ \App\Models\RecurringOperation::TYPE_EXPENSE }}" @selected($selectedType === \App\Models\RecurringOperation::TYPE_EXPENSE)>Expense</option>
        </select>
        <x-input-error :messages="$errors->get('type')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="amount" value="Amount" />
        <x-text-input id="amount" name="amount" type="text" class="mt-1 block w-full" :value="$defaultAmount" required />
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Enter a positive value, for example 10.00.</p>
        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="account_id" value="Account" />
        <select
            id="account_id"
            name="account_id"
            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm"
            required
        >
            <option value="">Select account</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" @selected((string) old('account_id', $operation->account_id ?? '') === (string) $account->id)>
                    {{ $account->name }} ({{ $account->currency }})
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('account_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="category_id" value="Category" />
        <select
            id="category_id"
            name="category_id"
            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm"
        >
            <option value="">No category</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) old('category_id', $operation->category_id ?? '') === (string) $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
    </div>

    <div class="flex justify-end gap-2">
        <a href="{{ route('recurring-operations.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-xs uppercase">Cancel</a>
        <x-primary-button>Save</x-primary-button>
    </div>
</form>
