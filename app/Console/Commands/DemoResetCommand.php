<?php

namespace App\Console\Commands;

use App\Demo\DemoGuard;
use App\Demo\DemoInventory;
use App\Demo\DemoPurgeFailedException;
use App\Demo\DemoPurger;
use App\Demo\DemoRefusedException;
use App\Demo\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class DemoResetCommand extends Command
{
    public const CONFIRMATION = 'Delete all of this demo data and rebuild it?';

    protected $signature = 'demo:reset {--force : Skip the confirmation prompt (safety checks still run)}';

    protected $description = 'Delete only the Pharmalab Demo College data and rebuild the full demo dataset (requires DEMO_MODE=true).';

    public function handle(DemoGuard $guard, DemoInventory $inventory, DemoPurger $purger, DemoSeeder $seeder): int
    {
        try {
            $password = $guard->password();
            $institution = $inventory->institution();
            if ($institution !== null) {
                $purger->assertSafe($institution);
            }
        } catch (DemoRefusedException $e) {
            $this->error('Demo reset refused: '.$e->getMessage().' No data was changed.');

            return self::FAILURE;
        }

        if ($institution !== null && ! $this->option('force')) {
            $this->line("Institution: {$institution->name} ({$institution->slug})");
            $this->table(['Will be removed', 'Count'], $inventory->summaryRows($institution));

            if (! $this->confirm(self::CONFIRMATION)) {
                $this->info('Demo reset cancelled. No data was changed.');

                return self::SUCCESS;
            }
        }

        try {
            // One outer transaction: if the rebuild fails, the purge rolls back
            // and the previous demo stays usable.
            DB::transaction(function () use ($institution, $purger, $seeder, $password): void {
                if ($institution !== null) {
                    $purger->purge($institution);
                }
                $seeder->seed($password);
            });
        } catch (DemoPurgeFailedException $e) {
            report($e);
            $this->error("Demo reset failed while deleting {$e->label}. No data was changed.");

            return self::FAILURE;
        } catch (DemoRefusedException $e) {
            $this->error('Demo reset refused: '.$e->getMessage().' No data was changed.');

            return self::FAILURE;
        } catch (Throwable $e) {
            report($e);
            $this->error("Demo reset failed while rebuilding the demo dataset ({$seeder->currentStep()}). No data was changed.");

            return self::FAILURE;
        }

        $rebuilt = $inventory->institution();
        $this->info('Demo reset complete.');
        if ($rebuilt !== null) {
            $this->table(['Demo data', 'Count'], $inventory->summaryRows($rebuilt));
        }

        return self::SUCCESS;
    }
}
