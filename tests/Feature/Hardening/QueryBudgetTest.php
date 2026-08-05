<?php

namespace Tests\Feature\Hardening;

use App\Enums\Role;
use App\Models\Course;
use App\Models\Programme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * N+1 audit of the heaviest pages (Section 15 hardening sweep).
 *
 * Each page is loaded twice, at two different data volumes. The assertion is not
 * "under N queries" — that would be an arbitrary number that drifts — but rather that
 * the count does not GROW MEANINGFULLY WITH THE ROW COUNT. That is the actual
 * definition of an N+1, and it is the only version of this test that stays honest as
 * the pages change.
 *
 * A small allowance is permitted: some pages legitimately issue one extra query per
 * distinct relation loaded, and eager loading itself costs one query per relation.
 */
class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    /** Queries the count may grow by between the small and large fixtures. */
    private const ALLOWED_GROWTH = 4;

    public function test_the_public_catalogue_does_not_n_plus_one(): void
    {
        $this->assertNoNPlusOne(
            fn () => $this->get(route('catalogue.index')),
            fn (int $n) => Course::factory()->count($n)->published()->create(),
        );
    }

    public function test_the_public_homepage_does_not_n_plus_one(): void
    {
        $this->assertNoNPlusOne(
            fn () => $this->get(route('home')),
            fn (int $n) => Course::factory()->count($n)->published()->create(),
        );
    }

    public function test_the_instructor_course_list_does_not_n_plus_one(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $this->assertNoNPlusOne(
            fn () => $this->actingAs($admin)->get(route('courses.index')),
            fn (int $n) => Course::factory()->count($n)->published()->create(),
        );
    }

    public function test_the_audit_viewer_does_not_n_plus_one(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        // Each programme write produces an audit entry with a causer and a subject —
        // exactly the two relations the list renders per row.
        $this->assertNoNPlusOne(
            fn () => $this->actingAs($admin)->get(route('admin.audit.index')),
            function (int $n) use ($admin) {
                $this->actingAs($admin);
                Programme::factory()->count($n)->create();
            },
        );
    }

    public function test_the_people_list_does_not_n_plus_one(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $this->assertNoNPlusOne(
            fn () => $this->actingAs($admin)->get(route('admin.users.index')),
            fn (int $n) => User::factory()->count($n)->create(),
        );
    }

    /**
     * Load the page at two data volumes and compare query counts.
     *
     * @param  callable():TestResponse  $load
     * @param  callable(int):void  $seed
     */
    private function assertNoNPlusOne(callable $load, callable $seed): void
    {
        $seed(3);
        $load()->assertSuccessful();          // warm caches/permissions first
        $small = $this->countQueries($load);

        $seed(12);
        $large = $this->countQueries($load);

        $growth = $large - $small;

        $this->assertLessThanOrEqual(
            self::ALLOWED_GROWTH,
            $growth,
            "Query count grew by {$growth} ({$small} → {$large}) when the row count went "
            .'from 3 to 15. That is an N+1: the page is querying per row instead of eager loading.',
        );
    }

    /**
     * @param  callable():TestResponse  $load
     */
    private function countQueries(callable $load): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $load()->assertSuccessful();

        $count = count(DB::getQueryLog());

        DB::disableQueryLog();
        DB::flushQueryLog();

        return $count;
    }
}
