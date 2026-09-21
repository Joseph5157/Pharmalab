<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesInstitutionAdministrator;

class RotationPolicy
{
    use AuthorizesInstitutionAdministrator;
}
