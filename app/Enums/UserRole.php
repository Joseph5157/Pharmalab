<?php

namespace App\Enums;

enum UserRole: string
{
    case Student = 'student';
    case Faculty = 'faculty';
    case Administrator = 'administrator';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Student',
            self::Faculty => 'Faculty / preceptor',
            self::Administrator => 'Institution administrator',
        };
    }

    public function dashboardRoute(): string
    {
        return match ($this) {
            self::Student => 'student.dashboard',
            self::Faculty => 'faculty.dashboard',
            self::Administrator => 'admin.dashboard',
        };
    }
}
