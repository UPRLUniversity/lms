<?php

namespace Tests\Feature\Reporting;

use App\Enums\CourseStatus;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_reports_real_platform_numbers(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $course = Course::factory()->published()->create();
        // 3 active + 1 completed → 4 active-or-completed; completion rate 1/4 = 25%.
        Enrollment::factory()->count(3)->active()->create(['course_id' => $course->id]);
        Enrollment::factory()->completed()->create([
            'course_id' => $course->id,
            'completed_at' => now()->subDay(),
        ]);
        // A course awaiting review shows in the pending queue.
        Course::factory()->create(['status' => CourseStatus::Review->value]);

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Active enrolments')
            ->assertSee('Completion rate')
            ->assertSee('25%')            // 1 completed of 4 seated
            ->assertSee('Enrolment trend')
            ->assertSee('Top courses')
            ->assertSee('Recent activity');
    }

    public function test_instructor_dashboard_shows_only_their_courses(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $mine = Course::factory()->published()->create(['title' => 'My Own Course', 'created_by' => $instructor->id]);
        $other = Course::factory()->published()->create(['title' => 'Someone Elses Course']);

        Enrollment::factory()->active()->create(['course_id' => $mine->id]);

        $this->actingAs($instructor)->get('/dashboard')
            ->assertOk()
            ->assertSee('My Own Course')
            ->assertSee('Average progress')
            ->assertDontSee('Someone Elses Course');
    }

    public function test_student_dashboard_shows_learning_and_grades(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create(['title' => 'Intro to PR']);
        Enrollment::factory()->active()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'progress_percent' => 40,
        ]);

        $this->actingAs($student)->get('/dashboard')
            ->assertOk()
            ->assertSee('Continue learning')
            ->assertSee('Intro to PR')
            ->assertSee('Recent grades')
            ->assertSee('Your certificates');
    }

    public function test_auditor_sees_the_admin_overview(): void
    {
        $auditor = $this->userWithRole(Role::Auditor->value);
        Course::factory()->published()->create();

        $this->actingAs($auditor)->get('/dashboard')
            ->assertOk()
            ->assertSee('Active enrolments')
            ->assertSee('Report centre');   // read-only access to the report suite
    }

    public function test_admin_dashboard_stays_within_the_query_budget(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $course = Course::factory()->published()->create();
        Enrollment::factory()->count(10)->active()->create(['course_id' => $course->id]);

        // Warm the 5-minute caches (stats/trend/top-courses) as a steady-state request would.
        $this->actingAs($admin)->get('/dashboard')->assertOk();

        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();
        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $queries = count(DB::connection()->getQueryLog());
        DB::connection()->disableQueryLog();

        $this->assertLessThanOrEqual(15, $queries, "Admin dashboard issued {$queries} queries (budget 15).");
    }
}
