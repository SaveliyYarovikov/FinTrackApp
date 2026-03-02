<?php

declare(strict_types=1);

use App\Support\Money;

if (! function_exists('money_format_minor')) {
    function money_format_minor(int $amountMinor, string $currency): string
    {
        return Money::fromMinor($amountMinor, $currency)->format();
    }
}
