<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Side of a keystone interface on a patch panel or wall outlet.
 * Only applies when the parent interface has type === InterfaceType::Keystone;
 * all other interface types must keep `side` null.
 */
enum InterfaceSide: string
{
    case Front = 'front';
    case Rear = 'rear';

    public function label(): string
    {
        return match ($this) {
            self::Front => 'Anteriore',
            self::Rear => 'Posteriore',
        };
    }

    public function opposite(): self
    {
        return match ($this) {
            self::Front => self::Rear,
            self::Rear => self::Front,
        };
    }
}
