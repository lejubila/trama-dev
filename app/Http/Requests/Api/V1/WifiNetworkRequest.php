<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\MakesOptionalOnUpdate;
use App\Models\WifiNetwork;
use App\Validation\WifiNetworkRules;
use Illuminate\Foundation\Http\FormRequest;

class WifiNetworkRequest extends FormRequest
{
    use MakesOptionalOnUpdate;

    public function authorize(): bool
    {
        if ($this->isMethod('POST')) {
            return $this->user()?->can('create', WifiNetwork::class) ?? false;
        }

        $net = $this->route('wifi_network');

        return $net instanceof WifiNetwork
            ? ($this->user()?->can('update', $net) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = WifiNetworkRules::rules();

        return $this->isMethod('POST') ? $rules : $this->makeOptional($rules);
    }
}
