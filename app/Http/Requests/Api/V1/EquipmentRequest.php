<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\MakesOptionalOnUpdate;
use App\Models\Equipment;
use App\Validation\EquipmentRules;
use Illuminate\Foundation\Http\FormRequest;

class EquipmentRequest extends FormRequest
{
    use MakesOptionalOnUpdate;

    public function authorize(): bool
    {
        if ($this->isMethod('POST')) {
            return $this->user()?->can('create', Equipment::class) ?? false;
        }

        $eq = $this->route('equipment');

        return $eq instanceof Equipment
            ? ($this->user()?->can('update', $eq) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = EquipmentRules::rules();

        return $this->isMethod('POST') ? $rules : $this->makeOptional($rules);
    }
}
