<?php

namespace App\Enums;

enum RentalStatus: string
{
    case Inactive = 'inactive';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Inactive => 'Inactive',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
        };
    }
}