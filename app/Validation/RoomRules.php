<?php

declare(strict_types=1);

namespace App\Validation;

class RoomRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'site_id' => 'required|integer|exists:sites,id',
            'name' => 'required|string|max:150',
            'floor' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:5000',
        ];
    }
}
