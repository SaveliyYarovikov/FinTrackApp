<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Create Account</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('accounts.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="currency" value="Currency" />
                        <select id="currency" name="currency" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency }}" @selected(old('currency', $defaultCurrency) === $currency)>{{ $currency }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('currency')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="type" value="Type" />
                        <select id="type" name="type" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                            @foreach ($accountTypes as $accountType)
                                <option value="{{ $accountType }}" @selected(old('type', 'card') === $accountType)>{{ ucfirst($accountType) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('type')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="balance" value="Balance" />
                        <x-text-input id="balance" name="balance" type="text" class="mt-1 block w-full" :value="old('balance', '0.00')" required />
                        <x-input-error :messages="$errors->get('balance')" class="mt-2" />
                    </div>

                    <div class="flex justify-end gap-2">
                        <a href="{{ route('accounts.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-xs uppercase">Cancel</a>
                        <x-primary-button>Create</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
