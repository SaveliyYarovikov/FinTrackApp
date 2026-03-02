@php
    $isCreateMode = ($operation ?? null) === null;
    $selectedType = old('type', $operation->type ?? \App\Models\RecurringOperation::TYPE_EXPENSE);
    if ($isCreateMode && $selectedType === \App\Models\RecurringOperation::TYPE_TRANSFER) {
        $selectedType = \App\Models\RecurringOperation::TYPE_EXPENSE;
    }
    $defaultAmount = old('amount', isset($operation) ? number_format($operation->amount_minor / 100, 2, '.', '') : '');
@endphp

<form method="POST" action="{{ $action }}" x-data="{ type: '{{ $selectedType }}' }" class="space-y-4">
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
            x-model="type"
            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm"
            required
        >
            <option value="{{ \App\Models\RecurringOperation::TYPE_INCOME }}">Income</option>
            <option value="{{ \App\Models\RecurringOperation::TYPE_EXPENSE }}">Expense</option>
            @if (! $isCreateMode)
                <option value="{{ \App\Models\RecurringOperation::TYPE_TRANSFER }}">Transfer</option>
            @endif
        </select>
        <x-input-error :messages="$errors->get('type')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="amount" value="Amount" />
        <x-text-input id="amount" name="amount" type="text" class="mt-1 block w-full" :value="$defaultAmount" required />
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Enter a positive value, for example 10.00.</p>
        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
    </div>

    <div x-show="type === '{{ \App\Models\RecurringOperation::TYPE_INCOME }}' || type === '{{ \App\Models\RecurringOperation::TYPE_EXPENSE }}'" x-cloak>
        <x-input-label for="account_id" value="Account" />
        <select
            id="account_id"
            name="account_id"
            x-bind:disabled="type === '{{ \App\Models\RecurringOperation::TYPE_TRANSFER }}'"
            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm"
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

    <div x-show="type === '{{ \App\Models\RecurringOperation::TYPE_INCOME }}' || type === '{{ \App\Models\RecurringOperation::TYPE_EXPENSE }}'" x-cloak>
        <x-input-label for="category_id" value="Category" />
        <select
            id="category_id"
            name="category_id"
            x-bind:disabled="type === '{{ \App\Models\RecurringOperation::TYPE_TRANSFER }}'"
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

    <div x-show="type === '{{ \App\Models\RecurringOperation::TYPE_TRANSFER }}'" x-cloak>
        <x-input-label for="from_account_id" value="From account" />
        <select
            id="from_account_id"
            name="from_account_id"
            x-bind:disabled="type !== '{{ \App\Models\RecurringOperation::TYPE_TRANSFER }}'"
            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm"
        >
            <option value="">Select source account</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" @selected((string) old('from_account_id', $operation->from_account_id ?? '') === (string) $account->id)>
                    {{ $account->name }} ({{ $account->currency }})
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('from_account_id')" class="mt-2" />
    </div>

    <div x-show="type === '{{ \App\Models\RecurringOperation::TYPE_TRANSFER }}'" x-cloak>
        <x-input-label for="to_account_id" value="To account" />
        <select
            id="to_account_id"
            name="to_account_id"
            x-bind:disabled="type !== '{{ \App\Models\RecurringOperation::TYPE_TRANSFER }}'"
            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm"
        >
            <option value="">Select destination account</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" @selected((string) old('to_account_id', $operation->to_account_id ?? '') === (string) $account->id)>
                    {{ $account->name }} ({{ $account->currency }})
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('to_account_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="description" value="Description" />
        <x-text-input id="description" name="description" type="text" class="mt-1 block w-full" :value="old('description', $operation->description ?? '')" />
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>

    <div class="flex justify-end gap-2">
        <a href="{{ route('recurring-operations.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-xs uppercase">Cancel</a>
        <x-primary-button>Save</x-primary-button>
    </div>
</form>
