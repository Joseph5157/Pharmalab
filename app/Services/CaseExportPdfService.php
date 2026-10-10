<?php

namespace App\Services;

use App\Models\CaseVersion;
use App\Models\ClinicalCase;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds the de-identified PDF of an approved case.
 *
 * Clinical content comes only from the approved CaseVersion's immutable
 * snapshot — never from live case tables — so a change made after approval
 * can never appear in an export. Header fields:
 *  - case number: clinical_cases.case_number (never changes);
 *  - version number and approval time: case_versions (immutable);
 *  - approver: case_versions.approved_by is an immutable user id; the name
 *    printed for it is looked up live from users, so it would follow a
 *    rename of that user (clinical_cases has no approved_by column);
 *  - rotation, site and ward: the names frozen in snapshot.record_metadata
 *    at submission; versions submitted before that block existed fall back
 *    to the current names, and the PDF says so.
 */
class CaseExportPdfService
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function approvedVersion(ClinicalCase $case): CaseVersion
    {
        $version = $case->versions()
            ->whereNotNull('approved_at')
            ->latest('version_number')
            ->first();

        abort_if($version === null, 409, 'This case has no approved version to export.');

        return $version;
    }

    /** The exact HTML handed to dompdf. */
    public function html(ClinicalCase $case): string
    {
        return view('exports.clinical-case', $this->viewData($case, $this->approvedVersion($case)))->render();
    }

    public function filename(ClinicalCase $case, CaseVersion $version): string
    {
        return "pharmalab-case-{$case->case_number}-v{$version->version_number}.pdf";
    }

    /** Render the PDF and record the export in the audit trail. */
    public function export(User $actor, ClinicalCase $case): Response
    {
        $version = $this->approvedVersion($case);
        $html = view('exports.clinical-case', $this->viewData($case, $version))->render();
        $pdf = Pdf::loadHTML($html)->setPaper('a4')->output();

        $this->audit->record($actor, $case, 'clinical_case.exported', [
            'case_id' => $case->id,
            'version_number' => $version->version_number,
            'case_version_id' => $version->id,
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($case, $version).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return array<string, mixed> */
    public function viewData(ClinicalCase $case, CaseVersion $version): array
    {
        $snapshot = $version->snapshot;
        $metadata = $snapshot['record_metadata'] ?? null;
        $legacyMetadata = ! is_array($metadata);

        if ($legacyMetadata) {
            $case->loadMissing(['rotationAssignment.rotation', 'clinicalSite', 'ward']);
            $metadata = [
                'rotation_name' => $case->rotationAssignment?->rotation?->name,
                'clinical_site_name' => $case->clinicalSite?->name,
                'ward_name' => $case->ward?->name,
            ];
        }

        $approver = $version->approved_by === null ? null : User::query()->withoutGlobalScopes()->find($version->approved_by);
        $student = User::query()->withoutGlobalScopes()->find($case->student_id);

        return [
            'header' => [
                'case_number' => $case->case_number,
                'version_number' => $version->version_number,
                'form_version' => $snapshot['form_version'] ?? null,
                'student_name' => $student?->name,
                'approver_name' => $approver?->name,
                'approved_at' => $this->dateTime($version->approved_at),
                'submitted_at' => $this->dateTime($version->submitted_at),
                'rotation_name' => $metadata['rotation_name'] ?? null,
                'clinical_site_name' => $metadata['clinical_site_name'] ?? null,
                'ward_name' => $metadata['ward_name'] ?? null,
            ],
            'legacyMetadata' => $legacyMetadata,
            'sections' => $this->sections($snapshot),
            'generatedAt' => $this->dateTime(now()),
        ];
    }

    /**
     * Snapshot sections as ordered [title => [rows|blocks]] ready for the
     * view. A "row" is [label, value]; a "block" is a titled list of rows for
     * one repeatable entry.
     *
     * @param  array<string, mixed>  $snapshot
     * @return list<array{title: string, rows: list<array{0: string, 1: string}>, blocks: list<array{title: string, rows: list<array{0: string, 1: string}>}>, empty: string|null}>
     */
    private function sections(array $snapshot): array
    {
        $sections = [];

        $sections[] = $this->section('Case context', $this->rows($snapshot['case_context'] ?? []));
        $sections[] = $this->section('History and diagnosis', $this->rows($snapshot['history_diagnosis'] ?? []), [], 'Not recorded.');

        foreach (['vitals' => 'Vitals', 'investigations' => 'Investigations'] as $key => $title) {
            $data = $snapshot[$key] ?? [];
            $sections[] = $this->section(
                $title,
                $this->rows(['status' => $data['status'] ?? null, 'unavailable_reason' => $data['unavailable_reason'] ?? null]),
                $this->blocks($data['entries'] ?? [], $key === 'vitals' ? 'observation_type' : 'test_name', $title === 'Vitals' ? 'Observation' : 'Test'),
            );
        }

        $sections[] = $this->section('Medication history', [], $this->blocks($snapshot['medication_history']['entries'] ?? [], 'generic_name', 'Medicine'), 'None recorded.');
        $chart = $snapshot['medication_chart'] ?? [];
        $sections[] = $this->section(
            'Medication chart',
            $this->rows(['status' => $chart['status'] ?? null, 'none_reason' => $chart['none_reason'] ?? null]),
            $this->blocks($chart['entries'] ?? [], 'generic_name', 'Medicine'),
        );

        $sections[] = $this->section('SOAP note', $this->rows($snapshot['soap'] ?? []), [], 'Not recorded.');

        $activities = array_values(array_map(fn (array $activity): array => [
            'title' => $this->label((string) ($activity['activity_type'] ?? 'Activity')),
            'rows' => $this->rows(['status' => $activity['status'] ?? null, ...(is_array($activity['details'] ?? null) ? $activity['details'] : [])]),
        ], $snapshot['clinical_activities'] ?? []));
        $sections[] = $this->section('Clinical activities', [], $activities, 'None recorded.');

        return $sections;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $rows
     * @param  list<array{title: string, rows: list<array{0: string, 1: string}>}>  $blocks
     * @return array{title: string, rows: list<array{0: string, 1: string}>, blocks: list<array{title: string, rows: list<array{0: string, 1: string}>}>, empty: string|null}
     */
    private function section(string $title, array $rows, array $blocks = [], ?string $whenEmpty = null): array
    {
        return [
            'title' => $title,
            'rows' => $rows,
            'blocks' => $blocks,
            'empty' => $rows === [] && $blocks === [] ? ($whenEmpty ?? 'Not recorded.') : null,
        ];
    }

    /**
     * @param  array<mixed>  $entries
     * @return list<array{title: string, rows: list<array{0: string, 1: string}>}>
     */
    private function blocks(array $entries, string $titleKey, string $fallbackTitle): array
    {
        $blocks = [];
        foreach (array_values($entries) as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $name = $entry[$titleKey] ?? null;
            $blocks[] = [
                'title' => ($index + 1).'. '.(is_string($name) && $name !== '' ? $this->label($name) : $fallbackTitle),
                'rows' => $this->rows($entry),
            ];
        }

        return $blocks;
    }

    /**
     * Label/value rows for every field that has a value; empty fields are
     * left out to keep the record readable.
     *
     * @param  array<mixed>|null  $data
     * @return list<array{0: string, 1: string}>
     */
    private function rows(?array $data): array
    {
        $rows = [];
        foreach ($data ?? [] as $key => $value) {
            $text = $this->value($value);
            if ($text === null) {
                continue;
            }
            $rows[] = [$this->label((string) $key), $text];
        }

        return $rows;
    }

    private function value(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $key => $item) {
                $text = $this->value($item);
                if ($text === null) {
                    continue;
                }
                $parts[] = is_string($key) ? $this->label($key).': '.$text : $text;
            }

            return $parts === [] ? null : implode(array_is_list($value) && ! $this->hasNestedArrays($value) ? ', ' : '; ', $parts);
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        // Stored option codes such as "no_known_allergy" read as words.
        return preg_match('/^[a-z]+(_[a-z]+)+$/', $text) === 1 ? Str::ucfirst(str_replace('_', ' ', $text)) : $text;
    }

    /** @param array<mixed> $value */
    private function hasNestedArrays(array $value): bool
    {
        foreach ($value as $item) {
            if (is_array($item)) {
                return true;
            }
        }

        return false;
    }

    private function label(string $key): string
    {
        return Str::ucfirst(str_replace('_', ' ', $key));
    }

    private function dateTime(?\DateTimeInterface $value): ?string
    {
        return $value === null ? null : Carbon::instance($value)->timezone(config('app.timezone'))->format('j M Y, H:i T');
    }
}
