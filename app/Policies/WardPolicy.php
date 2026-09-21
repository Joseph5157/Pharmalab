<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesInstitutionAdministrator;

class WardPolicy
{
    use AuthorizesInstitutionAdministrator;
}
