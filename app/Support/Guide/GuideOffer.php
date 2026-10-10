<?php

declare(strict_types=1);

namespace App\Support\Guide;

use App\Models\User;
use App\Support\Demo;

/**
 * The one-time "want a guided tour?" invitation. Remembered on the user,
 * except for the shared demo account, where it is per session so every
 * visitor gets it once.
 */
final class GuideOffer
{
    private const KEY = 'guide_offered';

    public static function pending(User $user): bool
    {
        if (! $user->canManageData()) {
            return false;
        }

        return Demo::enabled()
            ? ! session()->get(self::KEY, false)
            : ! data_get($user->preferences, self::KEY, false);
    }

    public static function dismiss(User $user): void
    {
        if (Demo::enabled()) {
            session()->put(self::KEY, true);

            return;
        }

        $user->forceFill(['preferences' => array_merge($user->preferences ?? [], [self::KEY => true])])->save();
    }
}
