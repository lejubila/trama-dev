<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\MakesOptionalOnUpdate;
use App\Models\Connection;
use App\Validation\ConnectionRules;
use Illuminate\Foundation\Http\FormRequest;

class ConnectionRequest extends FormRequest
{
    use MakesOptionalOnUpdate;

    public function authorize(): bool
    {
        if ($this->isMethod('POST')) {
            return $this->user()?->can('create', Connection::class) ?? false;
        }

        $c = $this->route('connection');

        return $c instanceof Connection
            ? ($this->user()?->can('update', $c) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ConnectionRules::rules();

        return $this->isMethod('POST') ? $rules : $this->makeOptional($rules);
    }
}
