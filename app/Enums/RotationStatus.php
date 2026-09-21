<?php

namespace App\Enums;

enum RotationStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
