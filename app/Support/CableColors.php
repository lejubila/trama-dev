<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Palette of preset cable colors offered by the connection forms.
 * Keys are user-facing labels, values are #RRGGBB hex codes that go
 * straight into the database column. Any other hex value (entered via
 * the custom picker) is equally accepted by the validator regex.
 */
final class CableColors
{
    /**
     * @return array<string, string>
     */
    public static function presets(): array
    {
        return [
            __('common.color_gray') => '#6B7280',
            __('common.color_black') => '#111827',
            __('common.color_white') => '#F3F4F6',
            __('common.color_red') => '#DC2626',
            __('common.color_orange') => '#EA580C',
            __('common.color_yellow') => '#EAB308',
            __('common.color_green') => '#16A34A',
            __('common.color_blue') => '#2563EB',
            __('common.color_purple') => '#9333EA',
            __('common.color_pink') => '#EC4899',
        ];
    }

    public const HEX_REGEX = '/^#[0-9A-Fa-f]{6}$/';
}
