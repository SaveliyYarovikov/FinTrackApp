<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Recurring Operations</h2>
            <a href="{{ route('recurring-operations.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                Add recurring operation
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                @if (session('status'))
                    <div class="mb-4 text-sm text-green-600 dark:text-green-400">{{ session('status') }}</div>
                @endif

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <th class="py-2 pr-4">Name</th>
                                <th class="py-2 pr-4">Type</th>
                                <th class="py-2 pr-4">Account(s)</th>
                                <th class="py-2 pr-4">Category</th>
                                <th class="py-2 pr-4">Amount</th>
                                <th class="py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-sm text-gray-900 dark:text-gray-100">
                        @forelse ($operations as $operation)
                            @php
                                $amount = number_format($operation->amount / 100, 2, '.', '');
                                $accountLabel = $operation->account?->name ?? '—';
                                $currency = $operation->account?->currency;

                                if ($currency !== null) {
                                    $amount = money_format_minor($operation->amount, $currency);
                                }
                            @endphp
                            <tr>
                                <td class="py-3 pr-4">{{ $operation->name }}</td>
                                <td class="py-3 pr-4">{{ ucfirst($operation->type) }}</td>
                                <td class="py-3 pr-4">{{ $accountLabel }}</td>
                                <td class="py-3 pr-4">{{ $operation->category?->name ?? '—' }}</td>
                                <td class="py-3 pr-4">{{ $amount }}</td>
                                <td class="py-3">
                                    <div class="flex items-center gap-3 text-xs uppercase">
                                        <a href="{{ route('recurring-operations.edit', $operation) }}" class="text-indigo-600 hover:text-indigo-500">Edit</a>
                                        <form method="POST" action="{{ route('recurring-operations.destroy', $operation) }}" onsubmit="return confirm('Delete recurring operation?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-500">DELETE</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">No recurring operations found.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $operations->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
