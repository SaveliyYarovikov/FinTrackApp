<?php

declare(strict_types=1);

namespace App\DTO;

final class CsvImportResult
{
    /**
     * @var array<int, array{row:int, message:string}>
     */
    public array $errors = [];

    public function __construct(
        public int $totalRows = 0,
        public int $importedRows = 0,
        public int $skippedRows = 0,
    ) {
    }

    public function addError(int $row, string $message): void
    {
        if (count($this->errors) >= 20) {
            return;
        }

        $this->errors[] = [
            'row' => $row,
            'message' => $message,
        ];
    }
}
