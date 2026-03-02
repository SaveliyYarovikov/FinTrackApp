<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounts;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Account $account */
        $account = $this->route('account');
        $userId = $this->user()->id;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('accounts')
                    ->ignore($account->id)
                    ->where(static function ($query) use ($userId): void {
                        $query->where('user_id', $userId);
                    }),
            ],
            'type' => ['required', 'string', Rule::in([Account::TYPE_CARD, Account::TYPE_SAVINGS])],
            'balance' => ['required', 'string', 'regex:/^-?\d+(\.\d{1,2})?$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => strtolower((string) $this->input('type', '')),
            'balance' => trim((string) $this->input('balance', '')),
        ]);
    }
}
