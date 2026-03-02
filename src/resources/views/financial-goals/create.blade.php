<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Add Goal</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                @if ($accounts->isEmpty())
                    <div class="text-sm text-amber-600 dark:text-amber-400">
                        No active savings accounts available. Create one before adding goals.
                    </div>
                @else
                    @include('financial-goals._form', [
                        'action' => route('financial-goals.store'),
                        'method' => 'POST',
                        'accounts' => $accounts,
                        'cancelRoute' => route('financial-goals.index'),
                        'submitLabel' => 'Create',
                    ])
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
