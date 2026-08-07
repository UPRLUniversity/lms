<?php

namespace Tests\Feature\Certificates;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\CourseGradeRecord;
use App\Models\Enrollment;
use App\Models\GradeScale;
use App\Models\Lesson;
use App\Models\Module;
use App\Services\Certificates\CertificateService;
use App\Services\Courses\LearningService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Certificate issuance on course completion: idempotent, serial-unique, and the
 * grade/template snapshot is frozen at the moment of issue — later edits to the live
 * scale or template never touch it. Admin manual issue/re-issue is covered too.
 */
class CertificateIssuanceTest extends TestCase
{
    use RefreshDatabase;

    private function scale(): GradeScale
    {
        $scale = GradeScale::factory()->default()->create(['scale_limit' => 5.0]);
        $scale->bands()->createMany([
            ['label' => 'A', 'grade_point' => 5.0, 'is_pass' => true, 'min_percent' => 70, 'max_percent' => 100, 'color' => 'success', 'position' => 0],
            ['label' => 'F', 'grade_point' => 0.0, 'is_pass' => false, 'min_percent' => 0, 'max_percent' => 69, 'color' => 'crimson', 'position' => 1],
        ]);

        return $scale;
    }

    private function completeWithGrade(int $percent = 90): array
    {
        $this->scale();
        CertificateTemplate::factory()->default()->create();

        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();
        $assessment = Assessment::factory()->published()->create(['course_id' => $course->id, 'is_required' => true, 'passing_score' => 50]);
        Enrollment::factory()->status(EnrollmentStatus::Active)->create(['user_id' => $student->id, 'course_id' => $course->id]);

        Attempt::factory()->create([
            'assessment_id' => $assessment->id,
            'user_id' => $student->id,
            'status' => 'graded',
            'score' => $percent,
            'max_score' => 100,
            'percentage' => $percent,
            'passed' => true,
        ]);

        app(LearningService::class)->recalculate($student, $course);

        return [$student, $course];
    }

    public function test_completion_issues_exactly_one_certificate_and_a_repeat_never_duplicates(): void
    {
        [$student, $course] = $this->completeWithGrade();

        $this->assertDatabaseCount('certificates', 1);
        $certificate = Certificate::first();
        $this->assertSame($student->id, $certificate->user_id);
        $this->assertSame($course->id, $certificate->course_id);
        $this->assertNotNull($certificate->rendered_at, 'the queued render should have run (sync queue in tests)');
        $this->assertNotNull($certificate->pdf(), 'a PDF media row should be attached');

        // Re-firing the completion pipeline (e.g. un-mark then re-complete) never duplicates.
        app(LearningService::class)->recalculate($student, $course);
        $this->assertDatabaseCount('certificates', 1);
    }

    public function test_serial_is_unique_and_follows_the_expected_format(): void
    {
        $this->completeWithGrade();

        $certificate = Certificate::first();
        $this->assertMatchesRegularExpression('/^UPRL-\d{4}-[A-Z0-9]{6}$/', $certificate->serial);

        // A duplicate serial can never be persisted (DB-level unique index).
        $this->expectException(QueryException::class);
        Certificate::factory()->create(['serial' => $certificate->serial]);
    }

    public function test_show_grade_true_prints_the_grade_recorded_at_completion(): void
    {
        [$student, $course] = $this->completeWithGrade(90);

        $certificate = Certificate::first();
        $this->assertTrue($certificate->snapshot['show_grade']);
        $this->assertSame('A', $certificate->snapshot['grade']['grade_label']);
        $this->assertSame('A · 5.0/5.0', $certificate->gradeLine());
    }

    public function test_show_grade_false_produces_no_grade_line(): void
    {
        $this->scale();
        CertificateTemplate::factory()->default()->withoutGrade()->create();

        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();
        $assessment = Assessment::factory()->published()->create(['course_id' => $course->id, 'is_required' => true, 'passing_score' => 50]);
        Enrollment::factory()->status(EnrollmentStatus::Active)->create(['user_id' => $student->id, 'course_id' => $course->id]);
        Attempt::factory()->create([
            'assessment_id' => $assessment->id, 'user_id' => $student->id, 'status' => 'graded',
            'score' => 90, 'max_score' => 100, 'percentage' => 90, 'passed' => true,
        ]);

        app(LearningService::class)->recalculate($student, $course);

        $certificate = Certificate::first();
        $this->assertFalse($certificate->snapshot['show_grade']);
        $this->assertNull($certificate->snapshot['grade']);
        $this->assertNull($certificate->gradeLine());
    }

    public function test_course_with_no_gradable_items_produces_a_certificate_with_no_grade_line(): void
    {
        $this->scale();
        CertificateTemplate::factory()->default()->create(); // show_grade = true

        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);
        Enrollment::factory()->status(EnrollmentStatus::Active)->create(['user_id' => $student->id, 'course_id' => $course->id]);

        // Completing the only lesson finishes the course — there are no required
        // assessments/assignments, so no CourseGradeRecord is ever written — but a
        // certificate is still issued, just with no grade line and no layout gap.
        app(LearningService::class)->markComplete($student, $lesson);

        $this->assertDatabaseCount('course_grade_records', 0);
        $this->assertDatabaseCount('certificates', 1);

        $certificate = Certificate::first();
        $this->assertTrue($certificate->snapshot['show_grade']);
        $this->assertNull($certificate->snapshot['grade']);
        $this->assertNull($certificate->gradeLine());
    }

    public function test_editing_the_grade_scale_after_issuance_does_not_change_the_certificate(): void
    {
        [$student, $course] = $this->completeWithGrade(90);

        $certificate = Certificate::first();
        $originalGrade = $certificate->snapshot['grade'];

        // Rewrite every band so 90% would now map to F instead of A.
        $scale = GradeScale::query()->default()->firstOrFail();
        $scale->bands()->delete();
        $scale->bands()->create(['label' => 'F', 'grade_point' => 0.0, 'is_pass' => false, 'min_percent' => 0, 'max_percent' => 100, 'color' => 'crimson', 'position' => 0]);

        $certificate->refresh();
        $this->assertSame($originalGrade, $certificate->snapshot['grade']);
        $this->assertSame('A · 5.0/5.0', $certificate->gradeLine());
    }

    public function test_editing_the_template_after_issuance_does_not_change_the_certificate_until_reissued(): void
    {
        [$student, $course] = $this->completeWithGrade(90);

        $certificate = Certificate::first();
        $this->assertSame('#C9A227', $certificate->snapshot['accent_color']);

        $template = CertificateTemplate::query()->default()->firstOrFail();
        $template->update(['config' => array_merge($template->config, ['accent_color' => '#000000'])]);

        $certificate->refresh();
        $this->assertSame('#C9A227', $certificate->snapshot['accent_color'], 'the issued snapshot must not drift when the live template changes');

        // An explicit re-issue DOES pick up the new template config.
        app(CertificateService::class)->reissue($certificate);
        $certificate->refresh();
        $this->assertSame('#000000', $certificate->snapshot['accent_color']);
    }

    public function test_admin_can_manually_issue_for_a_completed_student_without_one(): void
    {
        $this->scale();
        CertificateTemplate::factory()->default()->create();
        $admin = $this->userWithRole(Role::Admin->value);
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();
        Enrollment::factory()->completed()->create(['user_id' => $student->id, 'course_id' => $course->id]);

        $this->assertDatabaseCount('certificates', 0);

        $response = $this->actingAs($admin)->post(route('admin.certificates.issue'), [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('certificates', 1);
        $this->assertSame($student->id, Certificate::first()->user_id);
    }

    public function test_admin_reissue_keeps_the_same_serial_and_public_id(): void
    {
        [$student, $course] = $this->completeWithGrade(90);
        $certificate = Certificate::first();
        $originalSerial = $certificate->serial;
        $originalPublicId = $certificate->public_id;

        $admin = $this->userWithRole(Role::Admin->value);
        $this->actingAs($admin)->post(route('admin.certificates.reissue', $certificate));

        $certificate->refresh();
        $this->assertSame($originalSerial, $certificate->serial);
        $this->assertSame($originalPublicId, $certificate->public_id);
        $this->assertDatabaseCount('certificates', 1);
    }
}
