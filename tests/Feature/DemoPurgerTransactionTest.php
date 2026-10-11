<?php

namespace Tests\Feature;

use App\Demo\DemoDataset;
use App\Demo\DemoPurger;
use App\Demo\DemoRefusedException;
use App\Models\Institution;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Support\EnablesDemoMode;
use Tests\TestCase;

/**
 * RefreshDatabase wraps every test in a transaction, which would hide the
 * "outside a transaction" case. This class instead runs on its own temporary
 * SQLite file through a dedicated connection, so the purge really runs at
 * transaction level 0 and nothing it commits can reach the shared test
 * database other tests use (CI points every test at one SQLite file).
 */
class DemoPurgerTransactionTest extends TestCase
{
    use EnablesDemoMode;

    private const CONNECTION = 'demo_purger_test';

    private string $databaseFile;

    private string $originalDefault;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databaseFile = (string) tempnam(sys_get_temp_dir(), 'demo_purger_');
        $this->originalDefault = (string) config('database.default');

        config([
            'database.connections.'.self::CONNECTION => [
                'driver' => 'sqlite',
                'database' => $this->databaseFile,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'database.default' => self::CONNECTION,
        ]);
        DB::purge(self::CONNECTION);

        $this->artisan('migrate', ['--database' => self::CONNECTION])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        Auth::guard()->forgetUser();
        DB::disconnect(self::CONNECTION);
        DB::purge(self::CONNECTION);
        config(['database.default' => $this->originalDefault]);
        if (is_file($this->databaseFile)) {
            unlink($this->databaseFile);
        }

        parent::tearDown();
    }

    public function test_direct_purge_outside_a_transaction_changes_nothing(): void
    {
        $this->assertSame(self::CONNECTION, DB::getDefaultConnection());
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
