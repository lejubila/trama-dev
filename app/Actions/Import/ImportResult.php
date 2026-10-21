<?php

declare(strict_types=1);

namespace App\Actions\Import;

class ImportResult
{
    public int $created = 0;

    /**
     * @var list<array{row: int, messages: list<string>}>
     */
    public array $errors = [];

    public function incrementCreated(): void
    {
        $this->created++;
    }

    /**
     * @param  list<string>  $messages
     */
    public function addError(int $row, array $messages): void
    {
        $this->errors[] = ['row' => $row, 'messages' => $messages];
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return array{created: int, errors: list<array{row: int, messages: list<string>}>}
     */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'errors' => $this->errors,
        ];
    }
}
