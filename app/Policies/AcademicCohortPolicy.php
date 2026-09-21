<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesInstitutionAdministrator;

class AcademicCohortPolicy
{
    use AuthorizesInstitutionAdministrator;
}
