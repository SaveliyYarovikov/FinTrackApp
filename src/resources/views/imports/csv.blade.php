<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            CSV Import
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('imports.csv.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="csv" value="CSV file" />
                        <x-text-input id="csv" name="csv" type="file" class="mt-1 block w-full" accept=".csv,.txt" required />
                        <x-input-error :messages="$errors->get('csv')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="hidden" name="skip_duplicates" value="0">
                        <input
                            id="skip_duplicates"
                            type="checkbox"
                            name="skip_duplicates"
                            value="1"
                            @checked(old('skip_duplicates', $skipDuplicates ?? true))
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        >
                        <x-input-label for="skip_duplicates" value="Skip duplicates" />
                    </div>

                    <x-primary-button>Import</x-primary-button>
                </form>
            </div>

            @isset($result)
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 space-y-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Import summary</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                        <div class="rounded-md bg-gray-100 dark:bg-gray-700 p-3">
                            <p class="text-gray-500 dark:text-gray-400">Total rows</p>
                            <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $result->totalRows }}</p>
                        </div>
                        <div class="rounded-md bg-emerald-100 dark:bg-emerald-900/30 p-3">
                            <p class="text-emerald-700 dark:text-emerald-300">Imported</p>
                            <p class="font-semibold text-emerald-800 dark:text-emerald-200">{{ $result->importedRows }}</p>
                        </div>
                        <div class="rounded-md bg-amber-100 dark:bg-amber-900/30 p-3">
                            <p class="text-amber-700 dark:text-amber-300">Skipped</p>
                            <p class="font-semibold text-amber-800 dark:text-amber-200">{{ $result->skippedRows }}</p>
                        </div>
                    </div>

                    @if ($result->errors !== [])
                        <div class="rounded-md border border-red-300 bg-red-50 dark:bg-red-900/20 dark:border-red-800 p-4">
                            <p class="font-semibold text-red-800 dark:text-red-200 mb-2">First {{ count($result->errors) }} errors</p>
                            <ul class="list-disc list-inside text-sm text-red-700 dark:text-red-300 space-y-1">
                                @foreach ($result->errors as $error)
                                    <li>Row {{ $error['row'] }}: {{ $error['message'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endisset
        </div>
    </div>
</x-app-layout>
