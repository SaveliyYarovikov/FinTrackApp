<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Dashboard</h2>
    </x-slot>

    @php
        $formatMinorWithoutCurrency = static fn (int $amountMinor): string => number_format($amountMinor / 100, 2, '.', ',');
    @endphp

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Total balance</h3>

                @if ($accounts->isEmpty())
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No active accounts.</p>
                @else
                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">By currency</p>
                            <ul class="mt-3 space-y-2 text-sm text-gray-900 dark:text-gray-100">
                                @foreach ($currencyTotals as $currency => $totalMinor)
                                    <li class="flex items-center justify-between gap-3">
                                        <span class="font-medium">{{ $currency }}</span>
                                        <span>{{ money_format_minor((int) $totalMinor, (string) $currency) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Active accounts</p>
                            <ul class="mt-3 space-y-2 text-sm text-gray-900 dark:text-gray-100">
                                @foreach ($accounts as $account)
                                    <li class="flex items-center justify-between gap-3">
                                        <span class="font-medium">{{ $account->name }}</span>
                                        <span>{{ money_format_minor((int) $account->balance, $account->currency) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </section>

            <section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Filters</h3>

                <form method="GET" action="{{ route('dashboard') }}" class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Start date</label>
                        <input
                            id="start_date"
                            name="start_date"
                            type="date"
                            value="{{ old('start_date', $startDate) }}"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm"
                        >
                        <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                    </div>

                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">End date</label>
                        <input
                            id="end_date"
                            name="end_date"
                            type="date"
                            value="{{ old('end_date', $endDate) }}"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm"
                        >
                        <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
                    </div>

                    <div>
                        <label for="account_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Account</label>
                        <select
                            id="account_id"
                            name="account_id"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm"
                        >
                            <option value="" @selected($selectedAccountId === null)>All accounts</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" @selected((string) $selectedAccountId === (string) $account->id)>
                                    {{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('account_id')" class="mt-2" />
                    </div>

                    <div class="flex items-end">
                        <button
                            type="submit"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-indigo-500"
                        >
                            Apply
                        </button>
                    </div>
                </form>
            </section>

            <section class="grid gap-4 md:grid-cols-2">
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total income</p>
                    <p class="mt-2 text-2xl font-semibold text-emerald-600">
                        @if ($totalsCurrency !== null)
                            {{ money_format_minor($totalIncomeMinor, $totalsCurrency) }}
                        @else
                            {{ $formatMinorWithoutCurrency($totalIncomeMinor) }}
                        @endif
                    </p>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total expense</p>
                    <p class="mt-2 text-2xl font-semibold text-red-600">
                        @if ($totalsCurrency !== null)
                            {{ money_format_minor($totalExpenseMinorAbs, $totalsCurrency) }}
                        @else
                            {{ $formatMinorWithoutCurrency($totalExpenseMinorAbs) }}
                        @endif
                    </p>
                </div>
            </section>

            @if ($totalsCurrency === null)
                <p class="text-xs text-gray-500 dark:text-gray-400 px-1">Currency depends on account.</p>
            @endif

            <section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Expenses by category</h3>

                @if (empty($chartValuesMajor))
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No expenses in selected period.</p>
                @else
                    <div class="mt-4 h-80">
                        <canvas id="expensesByCategoryChart"></canvas>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        @if ($selectedAccount !== null)
                            Values are shown in major units ({{ $selectedAccount->currency }}).
                        @else
                            Values are shown in major units.
                        @endif
                    </p>
                @endif
            </section>
        </div>
    </div>

    @if (! empty($chartValuesMajor))
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const expenseChartLabels = @json($chartLabels);
            const expenseChartValues = @json($chartValuesMajor);
            const expenseChartCanvas = document.getElementById('expensesByCategoryChart');

            if (expenseChartCanvas && window.Chart) {
                const wrapLabelByWords = (rawLabel, maxLineLength = 12) => {
                    const label = String(rawLabel ?? '').trim();

                    if (label === '') {
                        return [''];
                    }

                    const words = label.split(/\s+/);
                    const lines = [];
                    let currentLine = '';

                    for (const word of words) {
                        const candidate = currentLine === '' ? word : `${currentLine} ${word}`;

                        if (candidate.length <= maxLineLength || currentLine === '') {
                            currentLine = candidate;
                            continue;
                        }

                        lines.push(currentLine);
                        currentLine = word;
                    }

                    if (currentLine !== '') {
                        lines.push(currentLine);
                    }

                    return lines;
                };

                new Chart(expenseChartCanvas, {
                    type: 'bar',
                    data: {
                        labels: expenseChartLabels,
                        datasets: [
                            {
                                data: expenseChartValues
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                ticks: {
                                    autoSkip: false,
                                    minRotation: 0,
                                    maxRotation: 0,
                                    callback(value) {
                                        return wrapLabelByWords(this.getLabelForValue(value));
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
        </script>
    @endif
</x-app-layout>
