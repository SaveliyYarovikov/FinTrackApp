@php
    $isCreateMode = ($goal ?? null) === null;
    $defaultAmount = old('amount', isset($goal) ? number_format((int) $goal->target_amount_minor / 100, 2, '.', '') : '');
    $selectedStatus = old('status', $goal->status ?? \App\Models\FinancialGoal::STATUS_ACTIVE);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-4">
    @csrf
    @if (($method ?? 'POST') !== 'POST')
        @method($method)
    @endif

    @if ($isCreateMode)
        <div>
            <x-input-label for="account_id" value="Savings account" />
            <select id="account_id" name="account_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm" required>
                <option value="">Select account</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" @selected((string) old('account_id') === (string) $account->id)>
                        {{ $account->name }} ({{ $account->currency }})
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('account_id')" class="mt-2" />
        </div>
    @else
        <div class="text-sm text-gray-500 dark:text-gray-400">
            Account: <strong>{{ $goal->account->name }} ({{ $goal->account->currency }})</strong>
            <span class="block">Account cannot be changed after goal creation.</span>
        </div>
    @endif

    <div>
        <x-input-label for="name" value="Name" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $goal->name ?? '')" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="amount" value="Target amount" />
        <x-text-input id="amount" name="amount" type="text" class="mt-1 block w-full" :value="$defaultAmount" required />
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Enter target as major value, for example 1500.00.</p>
        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="description" value="Description" />
        <textarea
            id="description"
            name="description"
            rows="3"
            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm"
        >{{ old('description', $goal->description ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="target_date" value="Target date" />
        <x-text-input
            id="target_date"
            name="target_date"
            type="date"
            class="mt-1 block w-full"
            :value="old('target_date', isset($goal) && $goal->target_date !== null ? $goal->target_date->toDateString() : '')"
        />
        <x-input-error :messages="$errors->get('target_date')" class="mt-2" />
    </div>

    @if (! $isCreateMode)
        <div>
            <x-input-label for="status" value="Status" />
            <select id="status" name="status" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                <option value="{{ \App\Models\FinancialGoal::STATUS_ACTIVE }}" @selected($selectedStatus === \App\Models\FinancialGoal::STATUS_ACTIVE)>Active</option>
                <option value="{{ \App\Models\FinancialGoal::STATUS_ACHIEVED }}" @selected($selectedStatus === \App\Models\FinancialGoal::STATUS_ACHIEVED)>Achieved</option>
                <option value="{{ \App\Models\FinancialGoal::STATUS_ARCHIVED }}" @selected($selectedStatus === \App\Models\FinancialGoal::STATUS_ARCHIVED)>Archived</option>
            </select>
            <x-input-error :messages="$errors->get('status')" class="mt-2" />
        </div>
    @endif

    <div class="flex justify-end gap-2">
        <a href="{{ $cancelRoute }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-xs uppercase">Cancel</a>
        <x-primary-button>{{ $submitLabel }}</x-primary-button>
    </div>
</form>
