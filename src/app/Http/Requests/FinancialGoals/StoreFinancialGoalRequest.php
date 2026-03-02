<?php

declare(strict_types=1);

namespace App\Http\Requests\FinancialGoals;

use App\Models\Account;
use App\Support\Money;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class StoreFinancialGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'account_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $account = Account::query()
                        ->whereKey((int) $value)
                        ->first();

                    if ($account === null) {
                        $fail('Selected account is invalid.');
                        return;
                    }

                    if ($account->user_id !== $this->user()->id) {
                        $fail('Selected account is invalid.');
                        return;
                    }

                    if ($account->type !== Account::TYPE_SAVINGS) {
                        $fail('Goal account must be a savings account.');
                        return;
                    }

                    if ($account->isArchived()) {
                        $fail('Archived account cannot be used.');
                    }
                },
            ],
            'amount' => [
                'required',
                'string',
                static function (string $attribute, mixed $value, Closure $fail): void {
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
            'description' => ['nullable', 'string', 'max:255'],
            'target_date' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $amount = $this->input('amount');
        $description = $this->input('description');

        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'amount' => is_string($amount) ? trim($amount) : $amount,
            'description' => is_string($description) ? trim($description) : $description,
            'account_id' => $this->normalizeNullableInt('account_id'),
        ]);
    }

    private function normalizeNullableInt(string $key): mixed
    {
        $value = $this->input($key);

        return $value === '' ? null : $value;
    }
}
