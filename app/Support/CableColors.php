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
            'Grigio' => '#6B7280',
            'Nero' => '#111827',
            'Bianco' => '#F3F4F6',
            'Rosso' => '#DC2626',
            'Arancione' => '#EA580C',
            'Giallo' => '#EAB308',
            'Verde' => '#16A34A',
            'Blu' => '#2563EB',
            'Viola' => '#9333EA',
            'Rosa' => '#EC4899',
        ];
    }

    public const HEX_REGEX = '/^#[0-9A-Fa-f]{6}$/';
}
