<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesInstitutionAdministrator;

class RotationAssignmentPolicy
{
    use AuthorizesInstitutionAdministrator;
}
