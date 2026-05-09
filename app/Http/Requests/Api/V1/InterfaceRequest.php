<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\MakesOptionalOnUpdate;
use App\Models\NetworkInterface;
use App\Validation\InterfaceRules;
use Illuminate\Foundation\Http\FormRequest;

class InterfaceRequest extends FormRequest
{
    use MakesOptionalOnUpdate;

    public function authorize(): bool
    {
        if ($this->isMethod('POST')) {
            return $this->user()?->can('create', NetworkInterface::class) ?? false;
        }

        $if = $this->route('interface');

        return $if instanceof NetworkInterface
            ? ($this->user()?->can('update', $if) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = InterfaceRules::rules();

        return $this->isMethod('POST') ? $rules : $this->makeOptional($rules);
    }
}
