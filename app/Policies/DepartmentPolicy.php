<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesInstitutionAdministrator;

class DepartmentPolicy
{
    use AuthorizesInstitutionAdministrator;
}
