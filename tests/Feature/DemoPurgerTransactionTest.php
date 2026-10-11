<?php

namespace Tests\Feature;

use App\Demo\DemoDataset;
use App\Demo\DemoPurger;
use App\Demo\DemoRefusedException;
use App\Models\Institution;
use Illuminate\Support\Facades\DB;
use Tests\Support\EnablesDemoMode;
use Tests\TestCase;

/**
 * RefreshDatabase wraps every test in a transaction, which would hide the
 * "outside a transaction" case. This class migrates its own fresh in-memory
 * SQLite database instead (discarded with the application after the test),
 * so the purge really runs at transaction level 0.
 */
class DemoPurgerTransactionTest extends TestCase
{
    use EnablesDemoMode;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh')->assertSuccessful();
    }

    public function test_direct_purge_outside_a_transaction_changes_nothing(): void
    {
        $this->enableDemoMode();
        $this->artisan('demo:seed')->assertSuccessful();
        $institution = Institution::query()->where('slug', DemoDataset::INSTITUTION_SLUG)->sole();
        $this->assertSame(0, DB::transactionLevel());
        $cases = DB::table('clinical_cases')->where('institution_id', $institution->id)->count();
        $users = DB::table('users')->where('institution_id', $institution->id)->count();

        try {
            app(DemoPurger::class)->purge($institution);
            $this->fail('Expected the purge to refuse outside a transaction.');
        } catch (DemoRefusedException $e) {
            $this->assertStringContainsString('inside a database transaction', $e->getMessage());
        }

        $this->assertNotNull(Institution::query()->find($institution->id));
        $this->assertSame($cases, DB::table('clinical_cases')->where('institution_id', $institution->id)->count());
        $this->assertSame($users, DB::table('users')->where('institution_id', $institution->id)->count());
    }
}
