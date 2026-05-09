<?php

declare(strict_types=1);

namespace App\Validation;

use App\Enums\InterfaceMedia;
use App\Enums\InterfacePoe;
use App\Enums\InterfaceStatus;
use App\Enums\InterfaceType;
use App\Enums\InterfaceVlanMode;
use Illuminate\Validation\Rule;

class InterfaceRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'equipment_id' => 'required|integer|exists:equipment,id',
            'name' => 'required|string|max:80',
            'type' => ['required', Rule::enum(InterfaceType::class)],
            'media' => ['required', Rule::enum(InterfaceMedia::class)],
            'index' => 'nullable|integer|min:0',
            'speed_mbps' => 'nullable|integer|min:1',
            'connector' => 'nullable|string|max:20',
            'vlan_mode' => ['nullable', Rule::enum(InterfaceVlanMode::class)],
            'vlan_default' => 'nullable|integer|min:1|max:4094',
            'vlans_allowed' => 'nullable|array',
            'vlans_allowed.*' => 'integer|min:1|max:4094',
            'ip_address' => 'nullable|string|max:45',
            'mac_address' => 'nullable|string|max:17',
            'status' => ['required', Rule::enum(InterfaceStatus::class)],
            'poe' => ['required', Rule::enum(InterfacePoe::class)],
            'description' => 'nullable|string|max:255',
        ];
    }
}
