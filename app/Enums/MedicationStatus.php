<?php

namespace App\Enums;

enum MedicationStatus: string
{
    case Active = 'active';
    case Stopped = 'stopped';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Prn = 'prn';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Stopped => 'Stopped',
            self::OnHold => 'On hold',
            self::Completed => 'Completed',
            self::Prn => 'PRN',
        };
    }
}
