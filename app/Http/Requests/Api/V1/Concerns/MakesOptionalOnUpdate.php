<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Concerns;

trait MakesOptionalOnUpdate
{
    /**
     * Strips `required` from each rule and prepends `sometimes` so
     * PATCH/PUT requests can submit a partial payload.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function makeOptional(array $rules): array
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
