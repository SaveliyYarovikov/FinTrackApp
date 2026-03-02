<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Transactions</h2>
            <div class="flex gap-2">
                <a href="{{ route('transactions.create-income') }}" class="inline-flex items-center px-3 py-2 bg-emerald-600 rounded-md text-xs font-semibold text-white uppercase">Income</a>
                <a href="{{ route('transactions.create-expense') }}" class="inline-flex items-center px-3 py-2 bg-red-600 rounded-md text-xs font-semibold text-white uppercase">Expense</a>
                <button
                    type="button"
                    x-data="{}"
                    @click="$dispatch('open-recurring-modal')"
                    class="inline-flex items-center px-3 py-2 bg-slate-700 rounded-md text-xs font-semibold text-white uppercase"
                >
                    Recurring
                </button>
                <a href="{{ route('imports.csv.create') }}" class="inline-flex items-center px-3 py-2 bg-blue-600 rounded-md text-xs font-semibold text-white uppercase">CSV Import</a>
            </div>
        </div>
    </x-slot>

    <div
        class="py-10"
        x-data="{
            open: {{ $errors->has('occurred_at') || $errors->has('recurring_operation') ? 'true' : 'false' }},
            selectedId: @js((string) old('selected_operation_id', '')),
            occurredAt: @js((string) old('occurred_at', now()->format('Y-m-d\\TH:i'))),
            baseApplyUrl: @js(url('/recurring-operations')),
            selectedEntryIds: [],
            manualSelectionUnlocked: false,
            pageEntryIds: @js($pageEntryIds),
            get applyAction() {
                return this.selectedId ? `${this.baseApplyUrl}/${this.selectedId}/apply` : '';
            },
            get allPageEntriesSelected() {
                return this.pageEntryIds.length > 0
                    && this.pageEntryIds.every((entryId) => this.selectedEntryIds.includes(entryId));
            },
            togglePageSelection(checked) {
                if (! this.manualSelectionUnlocked) {
                    return;
                }

                this.selectedEntryIds = checked ? [...this.pageEntryIds] : [];
            },
            isEntrySelected(entryId) {
                return this.selectedEntryIds.includes(String(entryId));
            },
        }"
        @open-recurring-modal.window="open = true"
        @keydown.escape.window="open = false"
    >
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                @if (session('status'))
                    <div class="mb-4 text-sm text-green-600 dark:text-green-400">{{ session('status') }}</div>
                @endif

                <x-input-error :messages="$errors->get('recurring_operation')" class="mb-4" />
                <x-input-error :messages="$errors->get('entry_ids')" class="mb-4" />

                <form method="GET" action="{{ route('transactions.index') }}" class="grid grid-cols-1 md:grid-cols-6 gap-3 mb-6">
                    <div>
                        <x-input-label for="from" value="From" />
                        <x-text-input id="from" name="from" type="date" class="mt-1 block w-full" :value="request('from')" />
                    </div>
                    <div>
                        <x-input-label for="to" value="To" />
                        <x-text-input id="to" name="to" type="date" class="mt-1 block w-full" :value="request('to')" />
                    </div>
                    <div>
                        <x-input-label for="account_id" value="Account" />
                        <select id="account_id" name="account_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                            <option value="">All</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" @selected((string) request('account_id') === (string) $account->id)>
                                    {{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="category_id" value="Category" />
                        <select id="category_id" name="category_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                            <option value="">All</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="type" value="Type" />
                        <select id="type" name="type" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                            <option value="">All</option>
                            @foreach (['income', 'expense'] as $type)
                                <option value="{{ $type }}" @selected(request('type') === $type)>{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 rounded-md text-xs font-semibold text-white uppercase">Filter</button>
                        <a href="{{ route('transactions.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-xs uppercase">Reset</a>
                    </div>
                </form>

                <form
                    method="POST"
                    action="{{ route('transactions.bulk-destroy') }}"
                    x-show="selectedEntryIds.length > 0"
                    x-cloak
                    @submit="if (!confirm('Delete selected entries?')) { $event.preventDefault(); }"
                    class="mb-4 flex flex-wrap items-center gap-2"
                >
                    @csrf
                    @method('DELETE')
                    <template x-for="id in selectedEntryIds" :key="id">
                        <input type="hidden" name="entry_ids[]" :value="id">
                    </template>

                    <span class="text-sm text-gray-600 dark:text-gray-300" x-text="`${selectedEntryIds.length} selected`"></span>
                    <button type="submit" class="inline-flex items-center px-3 py-2 bg-red-600 rounded-md text-xs font-semibold text-white uppercase">
                        Delete Selected
                    </button>
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <th class="py-2 pr-4 pl-2 w-10">
                                    <input
                                        type="checkbox"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 disabled:opacity-50"
                                        :disabled="!manualSelectionUnlocked || pageEntryIds.length === 0"
                                        :checked="allPageEntriesSelected"
                                        @change="togglePageSelection($event.target.checked)"
                                        title="Select all on page"
                                    />
                                </th>
                                <th class="py-2 pr-4">Date</th>
                                <th class="py-2 pr-4">Account</th>
                                <th class="py-2 pr-4">Category</th>
                                <th class="py-2 pr-4">Description</th>
                                <th class="py-2 pr-4">Type</th>
                                <th class="py-2 pr-4">Amount</th>
                                <th class="py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-sm text-gray-900 dark:text-gray-100">
                        @forelse ($entries as $entry)
                            <tr
                                :class="isEntrySelected({{ $entry->id }}) ? 'bg-slate-800 text-gray-100' : ''"
                                class="transition-colors"
                            >
                                <td class="py-3 pr-4 pl-2">
                                    <input
                                        type="checkbox"
                                        value="{{ $entry->id }}"
                                        x-model="selectedEntryIds"
                                        @change="
                                            if ($event.target.checked) {
                                                manualSelectionUnlocked = true;
                                            } else {
                                                $nextTick(() => {
                                                    if (selectedEntryIds.length === 0) {
                                                        $event.target.blur();
                                                    }
                                                });
                                            }
                                        "
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                    />
                                </td>
                                <td class="py-3 pr-4">{{ $entry->occurred_at->format('Y-m-d') }}</td>
                                <td class="py-3 pr-4">{{ $entry->account->name }}</td>
                                <td class="py-3 pr-4">{{ $entry->category?->name ?? '—' }}</td>
                                <td class="py-3 pr-4">{{ $entry->description ?? '—' }}</td>
                                <td class="py-3 pr-4">{{ ucfirst($entry->type) }}</td>
                                <td class="py-3 pr-4 {{ $entry->amount_minor >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                    {{ money_format_minor($entry->amount_minor, $entry->account->currency) }}
                                </td>
                                <td class="py-3">
                                    <div class="flex items-center gap-3">
                                        @if ($entry->transfer_id === null)
                                            <a href="{{ route('transactions.edit', $entry) }}" class="text-indigo-600 hover:text-indigo-500 text-xs uppercase">Edit</a>
                                        @endif

                                        <form method="POST" action="{{ route('transactions.destroy', $entry) }}" onsubmit="return confirm('Delete entry?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-500 text-xs uppercase">DELETE</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">No operations found.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $entries->links() }}</div>
            </div>
        </div>

        <div
            x-show="open"
            style="display: none;"
            class="fixed inset-0 z-50 overflow-y-auto"
            role="dialog"
            aria-modal="true"
        >
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50" @click="open = false"></div>

                <div class="relative w-full max-w-lg rounded-lg bg-white dark:bg-gray-800 shadow-xl p-6 space-y-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Apply Recurring Operation</h3>

                    <form method="POST" :action="applyAction" class="space-y-4">
                        @csrf

                        <div>
                            <x-input-label for="selected_operation_id" value="Recurring operation" />
                            <select
                                id="selected_operation_id"
                                name="selected_operation_id"
                                x-model="selectedId"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm"
                                required
                            >
                                <option value="">Select recurring operation</option>
                                @foreach ($recurringOperations as $operation)
                                    <option value="{{ $operation->id }}">{{ $operation->name }} ({{ ucfirst($operation->type) }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="recurring_occurred_at" value="Occurred at" />
                            <x-text-input
                                id="recurring_occurred_at"
                                name="occurred_at"
                                type="datetime-local"
                                class="mt-1 block w-full"
                                x-model="occurredAt"
                                required
                            />
                            <x-input-error :messages="$errors->get('occurred_at')" class="mt-2" />
                        </div>

                        <div class="flex justify-end gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-xs uppercase"
                                @click="open = false"
                            >
                                Cancel
                            </button>
                            <x-primary-button x-bind:disabled="!selectedId">Apply</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
