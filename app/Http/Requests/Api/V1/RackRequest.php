<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\MakesOptionalOnUpdate;
use App\Models\Rack;
use App\Validation\RackRules;
use Illuminate\Foundation\Http\FormRequest;

class RackRequest extends FormRequest
{
    use MakesOptionalOnUpdate;

    public function authorize(): bool
    {
        if ($this->isMethod('POST')) {
            return $this->user()?->can('create', Rack::class) ?? false;
        }

        $rack = $this->route('rack');

        return $rack instanceof Rack
            ? ($this->user()?->can('update', $rack) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = RackRules::rules();

        return $this->isMethod('POST') ? $rules : $this->makeOptional($rules);
    }
}
