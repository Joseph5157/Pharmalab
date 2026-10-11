<?php

namespace App\Demo;

use App\Enums\UserRole;

/**
 * The fixed identity of the demo dataset. These values define what demo:reset
 * is allowed to delete, so they are code, not configuration.
 */
final class DemoDataset
{
    public const INSTITUTION_SLUG = 'pharmalab-demo-college';

    public const INSTITUTION_NAME = 'Pharmalab Demo College';

    public const INSTITUTION_TIMEZONE = 'Asia/Kolkata';

    public const ADMIN = 'admin@pharmalab-demo.test';

    public const FACULTY = 'faculty@pharmalab-demo.test';

    public const STUDENT1 = 'student1@pharmalab-demo.test';

    public const STUDENT2 = 'student2@pharmalab-demo.test';

    public const PROGRAMME_CODE = 'PD-DEMO';

    public const PROGRAMME_NAME = 'Pharm.D';

    public const COHORT_NAME = '2022 Intake';

    public const SITE_CODE = 'DGH';

    public const SITE_NAME = 'Demo General Hospital';

    public const DEPARTMENT_NAME = 'General Medicine';

    public const WARD_CODE = 'MWA';

    public const WARD_NAME = 'Medicine Ward A';

    public const ROTATION_NAME = 'General Medicine Clerkship';

    public const ROTATION_STARTS_ON = '2022-01-01';

    public const ROTATION_ENDS_ON = '2032-12-31';

    /** @return array<string, array{name: string, role: UserRole}> */
    public static function users(): array
    {
        return [
            self::ADMIN => ['name' => 'Demo Administrator', 'role' => UserRole::Administrator],
            self::FACULTY => ['name' => 'Dr. Demo Preceptor', 'role' => UserRole::Faculty],
            self::STUDENT1 => ['name' => 'Priya Demo', 'role' => UserRole::Student],
            self::STUDENT2 => ['name' => 'Rahul Demo', 'role' => UserRole::Student],
        ];
    }

    /** @return list<array{category: string|null, target_count: int}> */
    public static function targets(): array
    {
        return [
            ['category' => null, 'target_count' => 6],
            ['category' => 'Cardiology', 'target_count' => 2],
            ['category' => 'Respiratory', 'target_count' => 2],
            ['category' => 'Infectious Disease', 'target_count' => 2],
        ];
    }
}
