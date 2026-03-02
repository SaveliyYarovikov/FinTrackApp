<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounts;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('accounts')->where(static function ($query) use ($userId): void {
                    $query->where('user_id', $userId);
                }),
            ],
            'currency' => ['required', 'string', 'size:3', Rule::in(config('fintrack.supported_currencies', []))],
            'type' => ['required', 'string', Rule::in([Account::TYPE_CARD, Account::TYPE_SAVINGS])],
            'opening_balance' => ['required', 'string', 'regex:/^-?\d+(\.\d{1,2})?$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => strtoupper((string) $this->input('currency', '')),
            'type' => strtolower((string) $this->input('type', '')),
        ]);
    }
}
