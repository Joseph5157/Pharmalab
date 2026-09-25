<?php

namespace App\Enums;

enum CaseFormVersion: string
{
    case PharmdV1 = 'pharmd-case-v1';

    public function label(): string
    {
        return match ($this) {
            self::PharmdV1 => 'Pharm.D Clinical Case — Form v1',
        };
    }
}
