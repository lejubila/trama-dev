<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\MakesOptionalOnUpdate;
use App\Models\VpnSiteToSite;
use App\Validation\VpnRules;
use Illuminate\Foundation\Http\FormRequest;

class VpnSiteToSiteRequest extends FormRequest
{
    use MakesOptionalOnUpdate;

    public function authorize(): bool
    {
        if ($this->isMethod('POST')) {
            return $this->user()?->can('create', VpnSiteToSite::class) ?? false;
        }

        $vpn = $this->route('vpn');

        return $vpn instanceof VpnSiteToSite
            ? ($this->user()?->can('update', $vpn) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = VpnRules::siteToSite();

        return $this->isMethod('POST') ? $rules : $this->makeOptional($rules);
    }
}
