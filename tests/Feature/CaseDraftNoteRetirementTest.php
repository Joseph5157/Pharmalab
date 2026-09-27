<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseDraftNoteRetirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sync_spike_routes_no_longer_exist(): void
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($student);

        $this->get('/student/sync-spike')->assertNotFound();
    }

    public function test_the_dashboard_still_renders_after_the_sync_spike_link_is_removed(): void
    {
        $institution = Institution::factory()->create();
        $student = User::factory()->student()->create(['institution_id' => $institution->id]);
        $this->actingAs($student);

        // A broken render here is the regression this catches: removing the
        // sync-spike card/import from Dashboard.vue must not leave the page
        // referencing a now-deleted component or route helper. The route's
        // absence itself is proven by the first test.
        $this->get('/student')->assertOk();
    }
}
