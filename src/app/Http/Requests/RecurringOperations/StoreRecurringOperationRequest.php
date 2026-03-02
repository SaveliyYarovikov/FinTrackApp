<?php

declare(strict_types=1);

namespace App\Http\Requests\RecurringOperations;

class StoreRecurringOperationRequest extends RecurringOperationRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
