<?php

namespace Tests\Feature\Authorization;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_queries_are_scoped_to_the_users_institution(): void
    {
        $firstInstitution = Institution::factory()->create();
        $secondInstitution = Institution::factory()->create();
        $administrator = User::factory()->administrator()->create(['institution_id' => $firstInstitution->id]);
        $sameInstitutionUser = User::factory()->create(['institution_id' => $firstInstitution->id]);
        $otherInstitutionUser = User::factory()->create(['institution_id' => $secondInstitution->id]);

        $this->actingAs($administrator);

        $this->assertTrue(User::query()->whereKey($sameInstitutionUser->id)->exists());
        $this->assertFalse(User::query()->whereKey($otherInstitutionUser->id)->exists());
    }

    public function test_policies_reject_cross_institution_records(): void
    {
        $firstInstitution = Institution::factory()->create();
        $secondInstitution = Institution::factory()->create();
        $administrator = User::factory()->administrator()->create(['institution_id' => $firstInstitution->id]);
        $sameInstitutionUser = User::factory()->create(['institution_id' => $firstInstitution->id]);
        $otherInstitutionUser = User::factory()->create(['institution_id' => $secondInstitution->id]);

        $this->assertTrue(Gate::forUser($administrator)->allows('view', $sameInstitutionUser));
        $this->assertFalse(Gate::forUser($administrator)->allows('view', $otherInstitutionUser));
        $this->assertTrue(Gate::forUser($administrator)->allows('view', $firstInstitution));
        $this->assertFalse(Gate::forUser($administrator)->allows('view', $secondInstitution));
    }
}
