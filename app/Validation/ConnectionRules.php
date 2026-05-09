<?php

declare(strict_types=1);

namespace App\Validation;

use App\Enums\ConnectionStatus;
use Illuminate\Validation\Rule;

class ConnectionRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'from_interface_id' => 'required|integer|exists:interfaces,id|different:to_interface_id',
            'to_interface_id' => 'required|integer|exists:interfaces,id',
            'cable_type' => 'required|string|max:30',
            'cable_length_m' => 'nullable|numeric|min:0',
            'cable_label' => 'nullable|string|max:80',
            'color' => 'nullable|string|max:20',
            'status' => ['nullable', Rule::enum(ConnectionStatus::class)],
            'notes' => 'nullable|string|max:5000',
            'established_at' => 'nullable|date',
        ];
    }
}
