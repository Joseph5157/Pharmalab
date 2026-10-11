<?php

namespace App\Console\Commands;

use App\Demo\DemoGuard;
use App\Demo\DemoInventory;
use App\Demo\DemoRefusedException;
use App\Demo\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class DemoSeedCommand extends Command
{
    protected $signature = 'demo:seed';

    protected $description = 'Create any missing Pharmalab Demo College data and rotate the demo passwords (requires DEMO_MODE=true).';

    public function handle(DemoGuard $guard, DemoSeeder $seeder, DemoInventory $inventory): int
    {
        try {
            $password = $guard->password();
            DB::transaction(fn () => $seeder->seed($password));
        } catch (DemoRefusedException $e) {
            $this->error('Demo seed refused: '.$e->getMessage().' No demo data was changed.');

            return self::FAILURE;
        } catch (Throwable $e) {
            report($e);
            $this->error("Demo seed failed while seeding {$seeder->currentStep()}. No demo data was changed.");

            return self::FAILURE;
        }

        $institution = $inventory->institution();
        if ($institution !== null) {
            $this->info("Demo data ready: {$institution->name} ({$institution->slug})");
            $this->table(['Demo data', 'Count'], $inventory->summaryRows($institution));
        }

        return self::SUCCESS;
    }
}
