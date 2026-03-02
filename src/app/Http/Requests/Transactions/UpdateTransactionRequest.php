<?php

declare(strict_types=1);

namespace App\Http\Requests\Transactions;

use App\Models\Category;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Transaction $transaction */
        $transaction = $this->route('entry');

        return [
            'category_id' => [
                'nullable',
                'integer',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $category = Category::query()->find((int) $value);

                    if ($category === null) {
                        $fail('Selected category does not exist.');
                    }
                },
            ],
            'amount' => [
                'required',
                'string',
                'regex:/^-?\d+(\.\d{1,2})?$/',
                static function (string $attribute, mixed $value, \Closure $fail) use ($transaction): void {
                    $amountMinor = Money::parseMajorToMinor((string) $value);

                    if ($transaction->type === Transaction::TYPE_INCOME && $amountMinor <= 0) {
                        $fail('Amount must be greater than zero.');
                        return;
                    }

                    if ($transaction->type === Transaction::TYPE_EXPENSE && $amountMinor >= 0) {
                        $fail('Amount must be less than zero.');
                        return;
                    }

                    if ($transaction->type === Transaction::TYPE_ADJUSTMENT && $amountMinor === 0) {
                        $fail('Amount must not be zero.');
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
