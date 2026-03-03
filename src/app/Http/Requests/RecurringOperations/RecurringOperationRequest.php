<?php

declare(strict_types=1);

namespace App\Http\Requests\RecurringOperations;

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringOperation;
use App\Support\Money;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

abstract class RecurringOperationRequest extends FormRequest
{
    private static ?bool $categoryTypeColumnExists = null;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => [
                'required',
                'string',
                Rule::in([
                    RecurringOperation::TYPE_INCOME,
                    RecurringOperation::TYPE_EXPENSE,
                ]),
            ],
            'amount' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    try {
                        $amountMinor = Money::parseMajorToMinor((string) $value);
                    } catch (InvalidArgumentException) {
                        $fail('Amount format is invalid.');
                        return;
                    }

                    if ($amountMinor <= 0) {
                        $fail('Amount must be greater than zero.');
                    }
                },
            ],
            'account_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $this->validateOwnedActiveAccount((int) $value, $fail, 'Selected account is invalid.');
                },
            ],
            'category_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $category = Category::query()
                        ->forUser($this->user()->id)
                        ->whereKey((int) $value)
                        ->first();

                    if ($category === null) {
                        $fail('Selected category is invalid.');
                        return;
                    }

                    if ($this->categoryTypeColumnExists() && $category->type !== null && $category->type !== $this->normalizedType()) {
                        $fail('Category type must match operation type.');
                    }
                },
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $amount = $this->input('amount');

        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'type' => $this->normalizedType(),
            'amount' => is_string($amount) ? trim($amount) : $amount,
            'account_id' => $this->normalizeNullableInt('account_id'),
            'category_id' => $this->normalizeNullableInt('category_id'),
        ]);
    }

    private function validateOwnedActiveAccount(int $accountId, Closure $fail, string $invalidMessage): void
    {
        $account = Account::query()
            ->where('user_id', $this->user()->id)
            ->whereKey($accountId)
            ->first();

        if ($account === null) {
            $fail($invalidMessage);
            return;
        }

        if ($account->isArchived()) {
            $fail('Archived account cannot be used.');
        }
    }

    private function normalizeNullableInt(string $key): mixed
    {
        $value = $this->input($key);

        return $value === '' ? null : $value;
    }

    private function normalizedType(): string
    {
        return strtolower(trim((string) $this->input('type', '')));
    }

    private function categoryTypeColumnExists(): bool
    {
        if (self::$categoryTypeColumnExists === null) {
            self::$categoryTypeColumnExists = Schema::hasColumn('categories', 'type');
        }

        return self::$categoryTypeColumnExists;
    }
}
