<?php

declare(strict_types=1);

namespace App\Http\Requests\Budgets;

use App\Models\Budget;
use App\Models\Category;
use App\Support\Money;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Budget $budget */
        $budget = $this->route('budget');
        $userId = $this->user()->id;
        $categoryId = (int) $this->input('category_id');
        $periodStart = (string) $this->input('period_start');
        $periodEnd = (string) $this->input('period_end');

        return [
            'period_start' => [
                'required',
                'date_format:Y-m-d',
            ],
            'period_end' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:period_start',
            ],
            'category_id' => [
                'required',
                'integer',
                'exists:categories,id',
                static function (string $attribute, mixed $value, Closure $fail) use ($userId): void {
                    $categoryExists = Category::query()
                        ->forUser($userId)
                        ->whereKey((int) $value)
                        ->exists();

                    if (! $categoryExists) {
                        $fail('Category must be an available category.');
                    }
                },
                Rule::unique('budgets')
                    ->ignore($budget->id)
                    ->where(static function ($query) use ($userId, $categoryId, $periodStart, $periodEnd): void {
                        $query
                            ->where('user_id', $userId)
                            ->where('category_id', $categoryId)
                            ->where('period_start', $periodStart)
                            ->where('period_end', $periodEnd);
                    }),
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
        ];
    }

    protected function prepareForValidation(): void
    {
        $amount = $this->input('amount');

        $this->merge([
            'period_start' => trim((string) $this->input('period_start', '')),
            'period_end' => trim((string) $this->input('period_end', '')),
            'amount' => is_string($amount) ? trim($amount) : $amount,
        ]);
    }
}
