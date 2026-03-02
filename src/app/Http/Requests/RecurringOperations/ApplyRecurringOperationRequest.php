<?php

declare(strict_types=1);

namespace App\Http\Requests\RecurringOperations;

use App\Models\RecurringOperation;
use Illuminate\Foundation\Http\FormRequest;

class ApplyRecurringOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var RecurringOperation|null $recurringOperation */
        $recurringOperation = $this->route('recurringOperation');

        if (! $recurringOperation instanceof RecurringOperation) {
            /** @var RecurringOperation|null $snakeCaseRecurringOperation */
            $snakeCaseRecurringOperation = $this->route('recurring_operation');
            $recurringOperation = $snakeCaseRecurringOperation;
        }

        return $recurringOperation !== null
            && $this->user() !== null
            && $this->user()->id === $recurringOperation->user_id;
    }

    public function rules(): array
    {
        return [
            'occurred_at' => ['required', 'date'],
            'selected_operation_id' => ['nullable', 'integer'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $occurredAt = $this->input('occurred_at');

        $this->merge([
            'occurred_at' => is_string($occurredAt) ? trim($occurredAt) : $occurredAt,
        ]);
    }
}
