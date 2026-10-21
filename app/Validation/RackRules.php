<?php

declare(strict_types=1);

namespace App\Validation;

use App\Enums\RackNumbering;
use Illuminate\Validation\Rule;

class RackRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'height_units' => 'required|integer|min:1|max:60',
            'width_mm' => 'nullable|integer|min:100|max:1500',
            'depth_mm' => 'nullable|integer|min:100|max:2000',
            'numbering' => ['nullable', Rule::enum(RackNumbering::class)],
            'notes' => 'nullable|string|max:5000',
            'position_x' => 'nullable|numeric|min:0|max:999.99',
            'position_y' => 'nullable|numeric|min:0|max:999.99',
        ];
    }
}
