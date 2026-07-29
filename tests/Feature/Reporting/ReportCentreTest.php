<?php

namespace Tests\Feature\Reporting;

use App\Enums\Role;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseGradeRecord;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\User;
use App\Reports\ComplianceReport;
use App\Reports\InstructorReport;
use App\Reports\LearnerReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportCentreTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Access control
    |--------------------------------------------------------------------------
    */

    public function test_student_cannot_access_the_report_centre(): void
    {
        $student = $this->userWithRole(Role::Student->value);

        $this->actingAs($student)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($student)->get(route('reports.show', 'learner'))->assertForbidden();
    }

    public function test_admin_and_auditor_can_open_the_report_centre(): void
    {
        foreach ([Role::Admin->value, Role::Auditor->value] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get(route('reports.index'))->assertOk()->assertSee('Report centre');
            $this->actingAs($user)->get(route('reports.show', 'learner'))->assertOk();
        }
    }

    public function test_unknown_report_key_404s(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $this->actingAs($admin)->get(route('reports.show', 'nonsense'))->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Learner report — filtering + grade columns
    |--------------------------------------------------------------------------
    */

    public function test_learner_report_filters_by_course(): void
    {
        $target = Course::factory()->published()->create(['title' => 'Target Course']);
        $other = Course::factory()->published()->create(['title' => 'Other Course']);

        $here = User::factory()->create(['name' => 'Enrolled Here']);
        $there = User::factory()->create(['name' => 'Enrolled Elsewhere']);
        Enrollment::factory()->active()->create(['user_id' => $here->id, 'course_id' => $target->id]);
        Enrollment::factory()->active()->create(['user_id' => $there->id, 'course_id' => $other->id]);

        // Assert at the report level — the filter dropdowns on the page echo every course
        // and user name, so a page-level assertDontSee cannot isolate the result rows.
        $names = collect((new LearnerReport)->rows(['course_id' => $target->id]))->pluck(0);

        $this->assertTrue($names->contains('Enrolled Here'));
        $this->assertFalse($names->contains('Enrolled Elsewhere'));

        // And the page renders with the filter applied.
        $admin = $this->userWithRole(Role::Admin->value);
        $this->actingAs($admin)
            ->get(route('reports.show', 'learner').'?apply=1&course_id='.$target->id)
            ->assertOk()
            ->assertSee('1 row matched');
    }

    public function test_learner_report_grade_columns_match_the_snapshot_and_blank_when_absent(): void
    {
        $course = Course::factory()->published()->create();

        // A completed student with a frozen grade snapshot.
        $completed = User::factory()->create(['name' => 'Graded Student']);
        Enrollment::factory()->completed()->create([
            'user_id' => $completed->id, 'course_id' => $course->id, 'completed_at' => now()->subDay(),
        ]);
        CourseGradeRecord::factory()->create([
            'user_id' => $completed->id,
            'course_id' => $course->id,
            'final_percent' => 82,
            'grade_label' => 'A',
            'grade_point' => 5.0,
        ]);

        // An in-progress student with NO record — grade cells must be blank, not 0.
        $inProgress = User::factory()->create(['name' => 'Active Student']);
        Enrollment::factory()->active()->create(['user_id' => $inProgress->id, 'course_id' => $course->id]);

        $report = new LearnerReport;
        $rows = collect($report->rows(['course_id' => $course->id]))->keyBy(0); // key by student name

        $gradedRow = $rows->get('Graded Student');
        $activeRow = $rows->get('Active Student');

        // Columns: 0 Student, 6 Final %, 7 Grade, 8 Grade point.
        $this->assertSame('82%', $gradedRow[6]);
        $this->assertSame('A', $gradedRow[7]);
        $this->assertSame('5.0', $gradedRow[8]);

        // The un-graded student: tidy empty strings, never "0".
        $this->assertSame('', $activeRow[6]);
        $this->assertSame('', $activeRow[7]);
        $this->assertSame('', $activeRow[8]);
    }

    /*
    |--------------------------------------------------------------------------
    | Certification report
    |--------------------------------------------------------------------------
    */

    public function test_certification_report_distinguishes_active_and_revoked(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $course = Course::factory()->published()->create();

        Certificate::factory()->create(['course_id' => $course->id, 'serial' => 'CERT-ACTIVE-1']);
        Certificate::factory()->revoked()->create(['course_id' => $course->id, 'serial' => 'CERT-REVOKED-1']);

        $this->actingAs($admin)
            ->get(route('reports.show', 'certification').'?apply=1&status=all')
            ->assertOk()
            ->assertSee('CERT-ACTIVE-1')
            ->assertSee('CERT-REVOKED-1')
            ->assertSee('Revoked');
    }

    /*
    |--------------------------------------------------------------------------
    | Compliance report — cross-product + percentages
    |--------------------------------------------------------------------------
    */

    public function test_compliance_report_classifies_a_cohort_against_a_course(): void
    {
        $department = Department::factory()->create();
        $course = Course::factory()->published()->create(['department_id' => $department->id]);

        $done = User::factory()->create(['name' => 'Finisher']);
        $doing = User::factory()->create(['name' => 'In Progress']);

        Enrollment::factory()->completed()->create(['user_id' => $done->id, 'course_id' => $course->id, 'completed_at' => now()]);
        Enrollment::factory()->active()->create(['user_id' => $doing->id, 'course_id' => $course->id, 'progress_percent' => 30]);

        $report = new ComplianceReport;
        $filters = ['course_ids' => [$course->id], 'cohort' => 'department', 'department_id' => $department->id];

        // Cohort = everyone enrolled in the department's courses = the two above.
        $this->assertSame(2, $report->count($filters));

        $summary = collect($report->summary($filters))->keyBy('label');
        $this->assertStringContainsString('50%', $summary->get('Completed')['value']);
        $this->assertStringContainsString('50%', $summary->get('In progress')['value']);

        $rows = collect($report->rows($filters))->keyBy(0);
        $this->assertSame('Completed', $rows->get('Finisher')[3]);
        $this->assertSame('In progress', $rows->get('In Progress')[3]);
    }

    public function test_compliance_email_cohort_reports_never_started(): void
    {
        $course = Course::factory()->published()->create();
        User::factory()->create(['name' => 'Known Person', 'email' => 'known@uprl.test']);

        $report = new ComplianceReport;
        $filters = [
            'course_ids' => [$course->id],
            'cohort' => 'emails',
            'emails' => 'known@uprl.test',
        ];

        $rows = collect($report->rows($filters));
        $this->assertCount(1, $rows);
        // Known person, never enrolled in the course → Never started.
        $this->assertSame('Never started', $rows->first()[3]);
    }

    /*
    |--------------------------------------------------------------------------
    | Instructor report — turnaround
    |--------------------------------------------------------------------------
    */

    public function test_instructor_report_lists_teaching_staff_with_enrolments(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value, ['name' => 'Prof Ada']);
        $course = Course::factory()->published()->create(['created_by' => $instructor->id]);
        Enrollment::factory()->count(3)->active()->create(['course_id' => $course->id]);

        $report = new InstructorReport;
        $rows = collect($report->rows([]))->keyBy(0);

        $this->assertArrayHasKey('Prof Ada', $rows->all());
        // Columns: 0 name, 2 courses, 3 enrolments.
        $this->assertSame('1', $rows->get('Prof Ada')[2]);
        $this->assertSame('3', $rows->get('Prof Ada')[3]);
    }

    public function test_running_a_report_without_applying_shows_the_prompt_not_results(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $this->actingAs($admin)->get(route('reports.show', 'learner'))
            ->assertOk()
            ->assertSee('Run report')
            ->assertSee('Set your filters');
    }
}
