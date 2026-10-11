<?php

namespace App\Demo;

use App\Models\Institution;
use Illuminate\Support\Facades\DB;

/**
 * Read-only views of the demo institution, for summaries and the reset
 * confirmation. Uses the query builder so no auth-dependent scope applies.
 */
class DemoInventory
{
    public function institution(): ?Institution
    {
        return Institution::query()->where('slug', DemoDataset::INSTITUTION_SLUG)->first();
    }

    /** @return array{users: int, cases: int, case_versions: int, review_comments: int, audit_events: int} */
    public function counts(Institution $institution): array
    {
        $count = fn (string $table): int => DB::table($table)->where('institution_id', $institution->id)->count();

        return [
            'users' => $count('users'),
            'cases' => $count('clinical_cases'),
            'case_versions' => $count('case_versions'),
            'review_comments' => $count('case_review_comments'),
            'audit_events' => $count('audit_events'),
        ];
    }

    /** @return list<array{0: string, 1: int}> */
    public function summaryRows(Institution $institution): array
    {
        $rows = [];
        foreach ($this->counts($institution) as $label => $count) {
            $rows[] = [str_replace('_', ' ', $label), $count];
        }

        $byStatus = DB::table('clinical_cases')
            ->where('institution_id', $institution->id)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->orderBy('status')
            ->pluck('aggregate', 'status');
        foreach ($byStatus as $status => $count) {
            $rows[] = ["cases: {$status}", (int) $count];
        }

        return $rows;
    }
}
