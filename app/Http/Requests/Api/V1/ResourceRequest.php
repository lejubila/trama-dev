<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create (POST) or update (PATCH/PUT) of one API resource: the policy decides,
 * and an update accepts a partial payload of the same rules.
 */
abstract class ResourceRequest extends FormRequest
{
    /** @var class-string<Model> */
    protected string $model;

    /** Route parameter holding the model on update. */
    protected string $routeKey;

    /**
     * @return array<string, mixed>
     */
    abstract protected function baseRules(): array;

    public function authorize(): bool
    {
        if ($this->isMethod('POST')) {
            return $this->user()?->can('create', $this->model) ?? false;
        }

        $model = $this->route($this->routeKey);

        return $model instanceof $this->model && ($this->user()?->can('update', $model) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = $this->baseRules();

        return $this->isMethod('POST') ? $rules : $this->makeOptional($rules);
    }

    /**
     * Strips `required` from each rule and prepends `sometimes`.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function makeOptional(array $rules): array
    {
        $out = [];
        foreach ($rules as $field => $rule) {
            $arr = is_array($rule) ? $rule : explode('|', (string) $rule);
            $arr = array_values(array_filter($arr, fn ($r) => $r !== 'required'));
            array_unshift($arr, 'sometimes');
            $out[$field] = $arr;
        }

        return $out;
    }
}
