<?php

declare(strict_types=1);

namespace App\Validation;

use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use Illuminate\Validation\Rule;

class EquipmentRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'type' => ['required', Rule::enum(EquipmentType::class)],
            'rack_id' => 'nullable|integer|exists:racks,id',
            'vendor' => 'nullable|string|max:80',
            'model' => 'nullable|string|max:120',
            'serial' => 'nullable|string|max:120',
            'firmware' => 'nullable|string|max:80',
            'asset_tag' => 'nullable|string|max:80',
            'mounted' => 'nullable|boolean',
            'locked' => 'nullable|boolean',
            'position_u_start' => 'nullable|integer|min:1|max:60',
            'position_u_height' => 'nullable|integer|min:1|max:60',
            'position_orient' => 'nullable|in:front,rear',
            'status' => ['nullable', Rule::enum(EquipmentStatus::class)],
            'management_ip' => 'nullable|ip',
            'description' => 'nullable|string|max:5000',
        ];
    }
}
