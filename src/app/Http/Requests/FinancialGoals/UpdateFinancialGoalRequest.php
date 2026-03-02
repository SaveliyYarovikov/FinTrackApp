<?php

declare(strict_types=1);

namespace App\Http\Requests\FinancialGoals;

use App\Models\FinancialGoal;
use App\Support\Money;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class UpdateFinancialGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'account_id' => ['prohibited'],
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
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    FinancialGoal::STATUS_ACTIVE,
                    FinancialGoal::STATUS_ACHIEVED,
                    FinancialGoal::STATUS_ARCHIVED,
                ]),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $amount = $this->input('amount');
        $description = $this->input('description');
        $status = $this->input('status');

        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'amount' => is_string($amount) ? trim($amount) : $amount,
            'description' => is_string($description) ? trim($description) : $description,
            'status' => is_string($status) && trim($status) !== ''
                ? strtolower(trim($status))
                : null,
        ]);
    }
}
