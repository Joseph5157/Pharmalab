<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesInstitutionAdministrator;

class ClinicalSitePolicy
{
    use AuthorizesInstitutionAdministrator;
}
