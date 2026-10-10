<?php

namespace App\Http\Controllers\Faculty;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Models\Rotation;
use App\Models\RotationCaseTarget;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RotationTargetController extends Controller
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $rotations = Rotation::query()
            ->whereHas('assignments', fn ($q) => $q
                ->where('primary_preceptor_id', $user->id)
                ->where('status', RecordStatus::Active))
            ->with(['clinicalSite', 'ward', 'targets' => fn ($q) => $q->orderByRaw('category IS NOT NULL')->orderBy('category')])
            ->withCount(['assignments as active_students_count' => fn ($q) => $q
                ->where('primary_preceptor_id', $user->id)
                ->where('status', RecordStatus::Active)])
            ->orderBy('starts_on')
            ->get()
            ->map(fn (Rotation $rotation): array => [
                'id' => $rotation->id,
                'name' => $rotation->name,
                'starts_on' => $rotation->starts_on->toDateString(),
                'ends_on' => $rotation->ends_on->toDateString(),
                'clinical_site_name' => $rotation->clinicalSite?->name,
                'ward_name' => $rotation->ward?->name,
                'active_students_count' => $rotation->getAttribute('active_students_count'),
                'targets' => $rotation->targets->map(fn (RotationCaseTarget $target): array => [
                    'id' => $target->id,
                    'category' => $target->category,
                    'target_count' => $target->target_count,
                ])->values(),
            ]);

        return Inertia::render('faculty/Rotations', [
            'rotations' => $rotations,
        ]);
    }

    public function store(Request $request, Rotation $rotation): RedirectResponse
    {
        Gate::authorize('manage', [RotationCaseTarget::class, $rotation]);
        $data = $this->validated($request);
        $this->assertUnique($rotation, $data['category'], null);

        $target = $this->persist(fn (): RotationCaseTarget => DB::transaction(function () use ($request, $rotation, $data): RotationCaseTarget {
            $target = RotationCaseTarget::query()->create([
                'institution_id' => $rotation->institution_id,
                'rotation_id' => $rotation->id,
                'category' => $data['category'],
                'target_count' => $data['target_count'],
                'created_by' => $request->user()->id,
            ]);
            $this->recordAudit($request->user(), $target, 'rotation_case_target.created');

            return $target;
        }), $data['category']);

        return back()->with('toast', ['type' => 'success', 'message' => $target->category === null ? 'Overall target added.' : 'Category target added.']);
    }

    public function update(Request $request, Rotation $rotation, RotationCaseTarget $target): RedirectResponse
    {
        Gate::authorize('manage', [RotationCaseTarget::class, $rotation]);
        $data = $this->validated($request);
        $this->assertUnique($rotation, $data['category'], $target->id);

        $this->persist(fn (): RotationCaseTarget => DB::transaction(function () use ($request, $target, $data): RotationCaseTarget {
            $previous = ['category' => $target->category, 'target_count' => $target->target_count];
            $target->update(['category' => $data['category'], 'target_count' => $data['target_count']]);
            $this->recordAudit($request->user(), $target, 'rotation_case_target.updated', ['previous' => $previous]);

            return $target;
        }), $data['category']);

        return back()->with('toast', ['type' => 'success', 'message' => 'Target updated.']);
    }

    public function destroy(Request $request, Rotation $rotation, RotationCaseTarget $target): RedirectResponse
    {
        Gate::authorize('manage', [RotationCaseTarget::class, $rotation]);

        DB::transaction(function () use ($request, $target): void {
            $this->recordAudit($request->user(), $target, 'rotation_case_target.deleted');
            $target->delete();
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Target removed.']);
    }

    /** @return array{category: string|null, target_count: int} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category' => ['nullable', 'string', 'max:80'],
            'target_count' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $category = isset($data['category']) ? trim((string) $data['category']) : null;

        return [
            'category' => $category === '' ? null : $category,
            'target_count' => (int) $data['target_count'],
        ];
    }

    /** The ordinary-case duplicate check, so the usual collision is a clean 422. */
    private function assertUnique(Rotation $rotation, ?string $category, ?string $ignoreId): void
    {
        $key = RotationCaseTarget::normalize($category);

        $duplicate = $rotation->targets()
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->get(['id', 'category'])
            ->contains(fn (RotationCaseTarget $existing): bool => RotationCaseTarget::normalize($existing->category) === $key);

        if ($duplicate) {
            $this->throwDuplicate($category);
        }
    }

    /**
     * Two simultaneous requests can both pass assertUnique(); the partial
     * unique indexes are the real guard. Convert their violation into the
     * same 422 instead of letting it surface as a 500.
     *
     * @param  callable(): RotationCaseTarget  $write
     */
    private function persist(callable $write, ?string $category): RotationCaseTarget
    {
        try {
            return $write();
        } catch (UniqueConstraintViolationException) {
            $this->throwDuplicate($category);
        }
    }

    private function throwDuplicate(?string $category): never
    {
        throw ValidationException::withMessages([
            'category' => $category === null
                ? 'This rotation already has an overall target. Edit it instead.'
                : 'This rotation already has a target for that category. Edit it instead.',
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function recordAudit(User $actor, RotationCaseTarget $target, string $event, array $extra = []): void
    {
        $this->audit->record($actor, $target, $event, [
            'rotation_id' => $target->rotation_id,
            'target_id' => $target->id,
            'category' => $target->category,
            'target_count' => $target->target_count,
            ...$extra,
        ]);
    }
}
