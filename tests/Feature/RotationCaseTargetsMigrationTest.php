<?php

namespace Tests\Feature;

use App\Models\Rotation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\BuildsSliceFiveFixtures;
use Tests\TestCase;

class RotationCaseTargetsMigrationTest extends TestCase
{
    use BuildsSliceFiveFixtures, RefreshDatabase;

    private const PATH = 'database/migrations/2026_10_10_000000_create_rotation_case_targets_table.php';

    public function test_rollback_drops_the_table_and_both_partial_indexes_and_remigration_restores_them(): void
    {
        $this->assertTrue(Schema::hasTable('rotation_case_targets'));
        $this->assertSame(['rotation_case_targets_category_unique', 'rotation_case_targets_overall_unique'], $this->partialIndexes());

        Artisan::call('migrate:rollback', ['--path' => self::PATH]);

        $this->assertFalse(Schema::hasTable('rotation_case_targets'));
        $this->assertSame([], $this->partialIndexes());

        Artisan::call('migrate', ['--path' => self::PATH]);

        $this->assertTrue(Schema::hasColumns('rotation_case_targets', ['category', 'category_key', 'target_count', 'created_by']));
        $this->assertSame(['rotation_case_targets_category_unique', 'rotation_case_targets_overall_unique'], $this->partialIndexes());
    }

    public function test_the_database_itself_rejects_a_second_overall_target(): void
    {
        [$rotation, $facultyId] = $this->rotationAndFaculty();
        $this->insertTarget($rotation->id, $rotation->institution_id, null, $facultyId);

        $this->expectException(QueryException::class);
        $this->insertTarget($rotation->id, $rotation->institution_id, null, $facultyId);
    }

    public function test_the_database_itself_rejects_a_case_or_whitespace_variant_of_an_existing_category(): void
    {
        [$rotation, $facultyId] = $this->rotationAndFaculty();
        $this->insertTarget($rotation->id, $rotation->institution_id, 'Cardiology', $facultyId);

        $this->assertSame('cardiology', DB::table('rotation_case_targets')->value('category_key'));

        $this->expectException(QueryException::class);
        $this->insertTarget($rotation->id, $rotation->institution_id, ' CARDIOLOGY ', $facultyId);
    }

    public function test_the_same_category_and_an_overall_target_may_exist_on_different_rotations(): void
    {
        [$rotation, $facultyId] = $this->rotationAndFaculty();
        $other = $this->makeRotation($this->institutionOf($rotation), 'Surgery');

        foreach ([$rotation, $other] as $r) {
            $this->insertTarget($r->id, $r->institution_id, null, $facultyId);
            $this->insertTarget($r->id, $r->institution_id, 'Cardiology', $facultyId);
        }

        $this->assertSame(4, DB::table('rotation_case_targets')->count());
    }

    /** @return list<string> */
    private function partialIndexes(): array
    {
        return collect(Schema::getIndexes('rotation_case_targets'))
            ->pluck('name')
            ->filter(fn (string $name): bool => str_ends_with($name, '_unique'))
            ->sort()
            ->values()
            ->all();
    }

    /** @return array{0: Rotation, 1: int} */
    private function rotationAndFaculty(): array
    {
        $rotation = $this->makeRotation();

        return [$rotation, $this->makeFaculty($rotation)->id];
    }

    private function insertTarget(string $rotationId, string $institutionId, ?string $category, int $facultyId): void
    {
        DB::table('rotation_case_targets')->insert([
            'id' => (string) Str::ulid(),
            'institution_id' => $institutionId,
            'rotation_id' => $rotationId,
            'category' => $category,
            'target_count' => 1,
            'created_by' => $facultyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
