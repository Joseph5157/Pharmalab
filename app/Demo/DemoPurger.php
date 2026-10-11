<?php

namespace App\Demo;

use App\Models\Institution;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Deletes only the demo institution's rows, child before parent, through an
 * explicit registry reconciled against the migrations. Foreign keys stay
 * restrictive: a row outside the demo institution that references a demo row
 * makes its stage fail, and the caller's transaction rolls everything back.
 */
class DemoPurger
{
    /** Tables with no link to demo data. */
    public const EXEMPT_TABLES = ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    /** Tables removed by an ON DELETE CASCADE foreign key: table => parent. */
    public const CASCADE_TABLES = ['passkeys' => 'users'];

    /** @return list<array{label: string, table: string, by: 'institution'|'user_ids'|'user_emails'|'id'}> */
    public static function registry(): array
    {
        return [
            ['label' => 'audit events', 'table' => 'audit_events', 'by' => 'institution'],
            ['label' => 'sync operations', 'table' => 'sync_operations', 'by' => 'institution'],
            ['label' => 'case review comments', 'table' => 'case_review_comments', 'by' => 'institution'],
            ['label' => 'case status transitions', 'table' => 'case_status_transitions', 'by' => 'institution'],
            ['label' => 'case versions', 'table' => 'case_versions', 'by' => 'institution'],
            ['label' => 'clinical activities', 'table' => 'case_clinical_activities', 'by' => 'institution'],
            ['label' => 'medications', 'table' => 'case_medications', 'by' => 'institution'],
            ['label' => 'investigations', 'table' => 'case_investigations', 'by' => 'institution'],
            ['label' => 'vitals', 'table' => 'case_vitals', 'by' => 'institution'],
            ['label' => 'clinical profiles', 'table' => 'case_clinical_profiles', 'by' => 'institution'],
            ['label' => 'SOAP notes', 'table' => 'soap_notes', 'by' => 'institution'],
            ['label' => 'case draft notes', 'table' => 'case_draft_notes', 'by' => 'institution'],
            ['label' => 'clinical cases', 'table' => 'clinical_cases', 'by' => 'institution'],
            ['label' => 'rotation case targets', 'table' => 'rotation_case_targets', 'by' => 'institution'],
            ['label' => 'rotation assignments', 'table' => 'rotation_assignments', 'by' => 'institution'],
            ['label' => 'rotations', 'table' => 'rotations', 'by' => 'institution'],
            ['label' => 'academic cohorts', 'table' => 'academic_cohorts', 'by' => 'institution'],
            ['label' => 'wards', 'table' => 'wards', 'by' => 'institution'],
            ['label' => 'departments', 'table' => 'departments', 'by' => 'institution'],
            ['label' => 'clinical sites', 'table' => 'clinical_sites', 'by' => 'institution'],
            ['label' => 'programmes', 'table' => 'programmes', 'by' => 'institution'],
            ['label' => 'demo user sessions', 'table' => 'sessions', 'by' => 'user_ids'],
            ['label' => 'demo password-reset tokens', 'table' => 'password_reset_tokens', 'by' => 'user_emails'],
            ['label' => 'demo users', 'table' => 'users', 'by' => 'institution'],
            ['label' => 'demo institution', 'table' => 'institutions', 'by' => 'id'],
        ];
    }

    /**
     * Every user in the demo institution must be on the allowlist (a subset is
     * fine, so a partly created demo can be recovered), and no allowlisted
     * email may be linked to another institution.
     */
    public function assertSafe(Institution $institution): void
    {
        if ($institution->slug !== DemoDataset::INSTITUTION_SLUG || $institution->name !== DemoDataset::INSTITUTION_NAME) {
            throw new DemoRefusedException('institution '.DemoDataset::INSTITUTION_SLUG.' exists with a different name.');
        }

        $allowlist = array_keys(DemoDataset::users());

        $unexpected = DB::table('users')->where('institution_id', $institution->id)->whereNotIn('email', $allowlist)->orderBy('email')->pluck('email')->all();
        if ($unexpected !== []) {
            throw new DemoRefusedException('the demo institution has users outside the demo allowlist: '.implode(', ', $unexpected).'.');
        }

        $elsewhere = DB::table('users')->whereIn('email', $allowlist)->where('institution_id', '!=', $institution->id)->orderBy('email')->pluck('email')->all();
        if ($elsewhere !== []) {
            throw new DemoRefusedException('demo accounts are linked to another institution: '.implode(', ', $elsewhere).'.');
        }
    }

    public function purge(Institution $institution): void
    {
        // Re-checked here, not only by the caller, so no code path can purge
        // anything but the exact demo institution with its exact allowlist.
        $this->assertSafe($institution);

        $users = DB::table('users')->where('institution_id', $institution->id);
        $userIds = (clone $users)->pluck('id')->all();
        $userEmails = (clone $users)->pluck('email')->all();

        foreach (self::registry() as $stage) {
            try {
                $query = DB::table($stage['table']);
                match ($stage['by']) {
                    'institution' => $query->where('institution_id', $institution->id)->delete(),
                    'user_ids' => $query->whereIn('user_id', $userIds)->delete(),
                    'user_emails' => $query->whereIn('email', $userEmails)->delete(),
                    'id' => $query->where('id', $institution->id)->delete(),
                };
            } catch (QueryException $e) {
                throw new DemoPurgeFailedException($stage['label'], $e);
            }
        }
    }
}
