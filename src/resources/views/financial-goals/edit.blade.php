<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Edit Goal</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                @include('financial-goals._form', [
                    'goal' => $goal,
                    'action' => route('financial-goals.update', $goal),
                    'method' => 'PUT',
                    'cancelRoute' => route('financial-goals.index'),
                    'submitLabel' => 'Save',
                ])
            </div>
        </div>
    </div>
</x-app-layout>
