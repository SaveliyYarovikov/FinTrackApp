<?php

declare(strict_types=1);

$currencyList = array_values(array_filter(array_map(
    static fn (string $currency): string => strtoupper(trim($currency)),
    explode(',', (string) env('FINTRACK_SUPPORTED_CURRENCIES', 'EUR,USD,RUB'))
)));

$accountTypeList = array_values(array_filter(array_map(
    static fn (string $type): string => strtolower(trim($type)),
    explode(',', (string) env('FINTRACK_ACCOUNT_TYPES', 'cash,card,bank,savings'))
)));

return [
    'default_currency' => strtoupper((string) env('FINTRACK_DEFAULT_CURRENCY', 'EUR')),
    'supported_currencies' => $currencyList === [] ? ['EUR'] : $currencyList,
    'account_types' => $accountTypeList === [] ? ['cash'] : $accountTypeList,
];
