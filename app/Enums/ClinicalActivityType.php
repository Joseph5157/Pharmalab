<?php

namespace App\Enums;

enum ClinicalActivityType: string
{
    case Intervention = 'intervention';
    case Adr = 'adr';
    case Counselling = 'counselling';
    case Monitoring = 'monitoring';

    public function label(): string
    {
        return match ($this) {
            self::Intervention => 'Pharmacist intervention',
            self::Adr => 'Suspected ADR',
            self::Counselling => 'Patient counselling',
            self::Monitoring => 'Monitoring follow-up',
        };
    }
}
