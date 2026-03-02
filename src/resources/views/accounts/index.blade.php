<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Accounts
            </h2>
            <a href="{{ route('accounts.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                New Account
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                @if (session('status'))
                    <div class="mb-4 text-sm text-green-600 dark:text-green-400">{{ session('status') }}</div>
                @endif

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <th class="py-2 pr-4">Name</th>
                                <th class="py-2 pr-4">Currency</th>
                                <th class="py-2 pr-4">Type</th>
                                <th class="py-2 pr-4">Balance</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-sm text-gray-900 dark:text-gray-100">
                        @forelse ($accounts as $account)
                            <tr>
                                <td class="py-3 pr-4">{{ $account->name }}</td>
                                <td class="py-3 pr-4">{{ $account->currency }}</td>
                                <td class="py-3 pr-4">{{ ucfirst($account->type) }}</td>
                                <td class="py-3 pr-4">{{ money_format_minor($account->balance_minor, $account->currency) }}</td>
                                <td class="py-3 pr-4">
                                    @if ($account->isArchived())
                                        <span class="inline-flex rounded-full bg-gray-200 px-2 py-1 text-xs text-gray-700">Archived</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-xs text-emerald-700">Active</span>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('accounts.edit', $account) }}" class="inline-flex items-center text-indigo-600 hover:text-indigo-500 text-xs uppercase">Edit</a>

                                        @if (! $account->isArchived())
                                            <form method="POST" action="{{ route('accounts.archive', $account) }}" class="inline-flex items-center">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center text-amber-600 hover:text-amber-500 text-xs uppercase">Archive</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">No accounts yet.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
