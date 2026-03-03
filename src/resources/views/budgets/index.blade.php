<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Budgets</h2>
            <a href="{{ route('budgets.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                Add budget
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                @if (session('status'))
                    <div class="mb-4 text-sm text-green-600 dark:text-green-400">{{ session('status') }}</div>
                @endif

                @php
                    $formatMinor = static fn (int $amountMinor): string => number_format($amountMinor / 100, 2, '.', '');
                @endphp

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <th class="py-2 pr-4">Category</th>
                                <th class="py-2 pr-4">Period</th>
                                <th class="py-2 pr-4">Limit</th>
                                <th class="py-2 pr-4">Spent</th>
                                <th class="py-2 pr-4">Remaining</th>
                                <th class="py-2 pr-4">Progress</th>
                                <th class="py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-sm text-gray-900 dark:text-gray-100">
                        @forelse ($rows as $row)
                            @php
                                $budget = $row['budget'];
                                $progressPercent = (float) $row['progress_percent'];
                                $progressWidth = (int) min(100, max(0, round($progressPercent)));
                            @endphp
                            <tr>
                                <td class="py-3 pr-4">{{ $row['category']->name }}</td>
                                <td class="py-3 pr-4">
                                    {{ $budget->period_start->toDateString() }} - {{ $budget->period_end->toDateString() }}
                                </td>
                                <td class="py-3 pr-4">{{ $formatMinor((int) $row['limit']) }}</td>
                                <td class="py-3 pr-4">{{ $formatMinor((int) $row['spent_minor']) }}</td>
                                <td class="py-3 pr-4 {{ $row['remaining_minor'] < 0 ? 'text-red-600' : 'text-emerald-600' }}">
                                    {{ $formatMinor((int) $row['remaining_minor']) }}
                                </td>
                                <td class="py-3 pr-4">
                                    <div class="w-44">
                                        <div class="h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                            <div
                                                class="h-2 rounded-full {{ $row['remaining_minor'] < 0 ? 'bg-red-500' : 'bg-indigo-600' }}"
                                                style="width: {{ $progressWidth }}%"
                                            ></div>
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ number_format($progressPercent, 2, '.', '') }}%</div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="flex items-center gap-3 text-xs uppercase">
                                        <a href="{{ route('budgets.edit', $budget) }}" class="text-indigo-600 hover:text-indigo-500">Edit</a>
                                        <form method="POST" action="{{ route('budgets.destroy', $budget) }}" onsubmit="return confirm('Delete budget?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-500">DELETE</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">No personal budgets</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
