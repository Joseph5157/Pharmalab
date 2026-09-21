<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesInstitutionAdministrator;

class ProgrammePolicy
{
    use AuthorizesInstitutionAdministrator;
}
