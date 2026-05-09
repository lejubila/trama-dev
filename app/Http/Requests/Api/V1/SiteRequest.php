<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\MakesOptionalOnUpdate;
use App\Models\Site;
use App\Validation\SiteRules;
use Illuminate\Foundation\Http\FormRequest;

class SiteRequest extends FormRequest
{
    use MakesOptionalOnUpdate;

    public function authorize(): bool
    {
        if ($this->isMethod('POST')) {
            return $this->user()?->can('create', Site::class) ?? false;
        }

        $site = $this->route('site');

        return $site instanceof Site
            ? ($this->user()?->can('update', $site) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = SiteRules::rules();

        return $this->isMethod('POST') ? $rules : $this->makeOptional($rules);
    }
}
