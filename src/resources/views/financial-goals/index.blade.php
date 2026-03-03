<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Goals</h2>
            <a href="{{ route('financial-goals.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                Add goal
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="flex justify-end">
                @if ($includeArchived)
                    <a href="{{ route('financial-goals.index') }}" class="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-500">
                        Hide archived
                    </a>
                @else
                    <a href="{{ route('financial-goals.index', ['archived' => 1]) }}" class="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-500">
                        Show archived
                    </a>
                @endif
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                @if (session('status'))
                    <div class="mb-4 text-sm text-green-600 dark:text-green-400">{{ session('status') }}</div>
                @endif

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <th class="py-2 pr-4">Name</th>
                                <th class="py-2 pr-4">Account</th>
                                <th class="py-2 pr-4">Target</th>
                                <th class="py-2 pr-4">Saved</th>
                                <th class="py-2 pr-4">Remaining</th>
                                <th class="py-2 pr-4">Progress</th>
                                <th class="py-2 pr-4">Target date</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-sm text-gray-900 dark:text-gray-100">
                        @forelse ($rows as $row)
                            @php
                                $goal = $row['goal'];
                                $account = $row['account'];
                                $currency = (string) $row['currency'];
                                $remainingMinor = max(0, (int) $row['remaining_minor']);
                                $progressWidth = max(0, min(100, (int) $row['progress_percent']));
                            @endphp
                            <tr>
                                <td class="py-3 pr-4">{{ $goal->name }}</td>
                                <td class="py-3 pr-4">{{ $account->name }}</td>
                                <td class="py-3 pr-4">{{ money_format_minor((int) $goal->target_amount, $currency) }}</td>
                                <td class="py-3 pr-4">{{ money_format_minor((int) $row['saved_minor'], $currency) }}</td>
                                <td class="py-3 pr-4">{{ money_format_minor($remainingMinor, $currency) }}</td>
                                <td class="py-3 pr-4">
                                    <div class="w-44">
                                        <div class="h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                            <div class="h-2 rounded-full bg-indigo-600" style="width: {{ $progressWidth }}%"></div>
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $progressWidth }}%</div>
                                    </div>
                                </td>
                                <td class="py-3 pr-4 {{ $row['is_overdue'] ? 'goal-target-date-overdue text-red-600 dark:text-red-400' : '' }}">
                                    {{ $goal->target_date?->toDateString() ?? '—' }}
                                </td>
                                <td class="py-3 pr-4">
                                    @if ($goal->status === \App\Models\FinancialGoal::STATUS_ARCHIVED)
                                        <span class="inline-flex rounded-full bg-gray-200 px-2 py-1 text-xs text-gray-700">Archived</span>
                                    @elseif ($goal->status === \App\Models\FinancialGoal::STATUS_ACHIEVED)
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-xs text-emerald-700">Achieved</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-indigo-100 px-2 py-1 text-xs text-indigo-700">Active</span>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <div class="flex items-center gap-3 text-xs uppercase">
                                        <a href="{{ route('financial-goals.edit', $goal) }}" class="text-indigo-600 hover:text-indigo-500">Edit</a>

                                        @if ($goal->status !== \App\Models\FinancialGoal::STATUS_ARCHIVED)
                                            <form method="POST" action="{{ route('financial-goals.archive', $goal) }}">
                                                @csrf
                                                <button type="submit" class="text-amber-600 hover:text-amber-500">ARCHIVE</button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('financial-goals.destroy', $goal) }}" onsubmit="return confirm('Delete goal?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-500">DELETE</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">No financial goals yet.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
