<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * IPv4 network in CIDR notation, e.g. "10.8.0.0/24".
 */
class Ipv4Cidr implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::passes($value)) {
            $fail('The :attribute must be an IPv4 network in CIDR notation (e.g. 10.0.0.0/24).');
        }
    }

    public static function passes(string $value): bool
    {
        $parts = explode('/', $value, 2);

        return count($parts) === 2
            && filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && ctype_digit($parts[1])
            && (int) $parts[1] <= 32;
    }
}
