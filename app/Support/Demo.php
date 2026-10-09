<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Single entry point for everything demo-mode related, so the rest of the
 * code asks "is this a demo?" instead of reading config keys directly.
 */
final class Demo
{
    /** Cache key holding the timestamp of the last completed reset. */
    public const LAST_RESET_KEY = 'demo:last_reset';

    public static function enabled(): bool
    {
        return (bool) config('demo.enabled');
    }

    /**
     * Abilities on the User model that the demo never allows, even to the
     * admin demo user: the shared account must stay usable for everyone.
     *
     * @var list<string>
     */
    public const LOCKED_USER_ABILITIES = ['viewAny', 'view', 'create', 'update', 'delete'];

    public static function nextResetAt(): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', (string) config('demo.reset_time', '03:00')) + [1 => 0]);
        $now = CarbonImmutable::now((string) config('demo.timezone', 'Europe/Rome'));
        $next = $now->setTime($hour, $minute);

        return $next->lessThanOrEqualTo($now) ? $next->addDay() : $next;
    }

    public static function resetTimeLabel(): string
    {
        return self::nextResetAt()->format('H:i');
    }

    public static function lastResetAt(): ?int
    {
        $value = Cache::get(self::LAST_RESET_KEY);

        return is_int($value) ? $value : null;
    }

    public static function markReset(): void
    {
        Cache::forever(self::LAST_RESET_KEY, now()->getTimestamp());
    }
}
