<?php

namespace App\Services;

use App\Enums\CaseStatus;
use App\Enums\RecordStatus;
use App\Models\ClinicalCase;
use App\Models\RotationAssignment;
use App\Models\RotationCaseTarget;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Progress against faculty-set rotation targets.
 *
 * Counting is always per rotation assignment (one student on one rotation):
 * a target of "Cardiology: 3" means each assigned student individually needs
 * three approved Cardiology cases. Only Approved cases count, so a target is
 * met only by faculty-signed-off work.
 *
 * @phpstan-type CategoryProgress array{category: string, approved: int, target: int|null}
 * @phpstan-type AssignmentProgress array{
 *     assignment_id: string,
 *     status: string,
 *     student: array{id: int, name: string},
 *     rotation: array{id: string, name: string, starts_on: string, ends_on: string},
 *     overall: array{approved: int, target: int|null},
 *     categories: list<CategoryProgress>,
 *     uncategorised_approved: int,
 * }
 */
class RotationProgressService
{
    /** @return Collection<int, AssignmentProgress> */
    public function forStudent(User $student): Collection
    {
        return $this->build(
            RotationAssignment::query()
                ->where('institution_id', $student->institution_id)
                ->where('student_id', $student->id)
        );
    }

    /** @return Collection<int, AssignmentProgress> */
    public function forFaculty(User $faculty): Collection
    {
        return $this->build(
            RotationAssignment::query()
                ->where('institution_id', $faculty->institution_id)
                ->where('primary_preceptor_id', $faculty->id)
        );
    }

    /** @return Collection<int, AssignmentProgress> */
    public function forInstitution(string $institutionId, ?string $rotationId = null): Collection
    {
        return $this->build(
            RotationAssignment::query()
                ->where('institution_id', $institutionId)
                ->when($rotationId !== null, fn (Builder $q) => $q->where('rotation_id', $rotationId))
        );
    }

    /**
     * The single-assignment entry, for the student dashboard.
     *
     * @return AssignmentProgress|null
     */
    public function forAssignment(RotationAssignment $assignment): ?array
    {
        return $this->build(RotationAssignment::query()->whereKey($assignment->id))->first();
    }

    /**
     * @param  Builder<RotationAssignment>  $assignments
     * @return Collection<int, AssignmentProgress>
     */
    private function build(Builder $assignments): Collection
    {
        $rows = $assignments
            ->with(['student:id,name', 'rotation.targets'])
            ->get()
            ->filter(fn (RotationAssignment $a): bool => $a->rotation !== null && $a->student !== null)
            ->sortBy([
                fn (RotationAssignment $a, RotationAssignment $b): int => ($a->status === RecordStatus::Active ? 0 : 1) <=> ($b->status === RecordStatus::Active ? 0 : 1),
                fn (RotationAssignment $a, RotationAssignment $b): int => [$a->rotation?->name, $a->student?->name] <=> [$b->rotation?->name, $b->student?->name],
            ])
            ->values();

        $approvedByAssignment = ClinicalCase::query()
            ->whereIn('rotation_assignment_id', $rows->pluck('id'))
            ->where('status', CaseStatus::Approved)
            ->get(['rotation_assignment_id', 'case_category'])
            ->groupBy('rotation_assignment_id');

        return $rows->map(function (RotationAssignment $assignment) use ($approvedByAssignment): array {
            /** @var Collection<int, ClinicalCase> $approved */
            $approved = $approvedByAssignment->get($assignment->id, collect());

            return $this->entry($assignment, $approved);
        });
    }

    /**
     * @param  Collection<int, ClinicalCase>  $approved
     * @return AssignmentProgress
     */
    private function entry(RotationAssignment $assignment, Collection $approved): array
    {
        $rotation = $assignment->rotation;
        $student = $assignment->student;
        abort_unless($rotation !== null && $student !== null, 500, 'Progress requires a rotation and a student.');

        $overallTarget = $rotation->targets->first(fn (RotationCaseTarget $t): bool => $t->category === null);

        // Approved counts by normalized category, remembering the first
        // casing seen for categories that have no target.
        $counts = [];
        $labels = [];
        $uncategorised = 0;
        foreach ($approved as $case) {
            $key = RotationCaseTarget::normalize($case->case_category);
            if ($key === null) {
                $uncategorised++;

                continue;
            }
            $counts[$key] = ($counts[$key] ?? 0) + 1;
            $labels[$key] ??= trim((string) $case->case_category);
        }

        $categories = [];
        $categoryTargets = $rotation->targets
            ->filter(fn (RotationCaseTarget $t): bool => $t->category !== null)
            ->sortBy(fn (RotationCaseTarget $t): string => mb_strtolower((string) $t->category));
        foreach ($categoryTargets as $target) {
            $key = (string) RotationCaseTarget::normalize($target->category);
            $categories[] = ['category' => (string) $target->category, 'approved' => $counts[$key] ?? 0, 'target' => $target->target_count];
            unset($counts[$key]);
        }

        // A category with approved cases but no target (for example entered
        // as free text before targets existed) is shown, never dropped.
        ksort($counts);
        foreach ($counts as $key => $count) {
            $categories[] = ['category' => $labels[$key], 'approved' => $count, 'target' => null];
        }

        return [
            'assignment_id' => $assignment->id,
            'status' => $assignment->status->value,
            'student' => ['id' => $student->id, 'name' => $student->name],
            'rotation' => [
                'id' => $rotation->id,
                'name' => $rotation->name,
                'starts_on' => $rotation->starts_on->toDateString(),
                'ends_on' => $rotation->ends_on->toDateString(),
            ],
            'overall' => ['approved' => $approved->count(), 'target' => $overallTarget?->target_count],
            'categories' => $categories,
            'uncategorised_approved' => $uncategorised,
        ];
    }
}
