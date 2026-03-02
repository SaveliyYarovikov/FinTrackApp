<?php

declare(strict_types=1);

namespace App\Http\Requests\RecurringOperations;

use App\Models\RecurringOperation;

class UpdateRecurringOperationRequest extends RecurringOperationRequest
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
}
