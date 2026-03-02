<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public function __construct(
        public readonly int $amountMinor,
        public readonly string $currency,
    ) {
        if ($this->currency === '') {
            throw new InvalidArgumentException('Currency must be provided.');
        }
    }

    public static function fromMinor(int $amountMinor, string $currency): self
    {
        return new self($amountMinor, strtoupper($currency));
    }

    public static function fromMajor(string|int $amountMajor, string $currency): self
    {
        return new self(self::parseMajorToMinor($amountMajor), strtoupper($currency));
    }

    public static function parseMajorToMinor(string|int $value): int
    {
        if (is_int($value)) {
            return $value * 100;
        }

        $normalized = str_replace(',', '.', trim($value));

        if (! preg_match('/^-?\d+(?:\.\d{1,2})?$/', $normalized)) {
            throw new InvalidArgumentException('Amount must have at most two decimal places.');
        }

        $negative = str_starts_with($normalized, '-');
        $unsigned = ltrim($normalized, '-');
        [$major, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '0');

        $minor = ((int) $major * 100) + (int) str_pad($fraction, 2, '0');

        return $negative ? -$minor : $minor;
    }

    public function add(self $other): self
    {
        $this->ensureSameCurrency($other);

        return new self($this->amountMinor + $other->amountMinor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->ensureSameCurrency($other);

        return new self($this->amountMinor - $other->amountMinor, $this->currency);
    }

    public function negate(): self
    {
        return new self(-$this->amountMinor, $this->currency);
    }

    public function format(): string
    {
        $absoluteMinor = abs($this->amountMinor);
        $major = intdiv($absoluteMinor, 100);
        $fraction = str_pad((string) ($absoluteMinor % 100), 2, '0', STR_PAD_LEFT);
        $sign = $this->amountMinor < 0 ? '-' : '';

        return sprintf('%s%s.%s %s', $sign, number_format($major), $fraction, $this->currency);
    }

    private function ensureSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currency mismatch.');
        }
    }
}
