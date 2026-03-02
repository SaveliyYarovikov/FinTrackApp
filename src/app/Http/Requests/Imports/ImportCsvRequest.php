<?php

declare(strict_types=1);

namespace App\Http\Requests\Imports;

use Illuminate\Foundation\Http\FormRequest;

class ImportCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'csv' => [
                'required',
                'file',
                'max:10240',
                'mimes:csv,txt',
            ],
            'skip_duplicates' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'skip_duplicates' => $this->boolean('skip_duplicates', true),
        ]);
    }
}
