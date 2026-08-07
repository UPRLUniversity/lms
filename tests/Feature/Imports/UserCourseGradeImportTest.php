<?php

namespace Tests\Feature\Imports;

use App\Enums\CourseStatus;
use App\Enums\Role;
use App\Imports\CourseImport;
use App\Imports\GradeImport;
use App\Imports\UserImport;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Programme;
use App\Models\ProgrammePart;
use App\Models\Submission;
use App\Models\User;
use App\Support\Import\ImportDefinition;
use App\Support\Import\ImportFormatException;
use App\Support\Import\ImportRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserCourseGradeImportTest extends TestCase
{
    use RefreshDatabase;

    private function analyse(ImportDefinition $definition, string $csv, ?Model $scope, User $actor): array
    {
        return app(ImportRunner::class)->analyse($definition, $this->file($csv), $scope, $actor);
    }

    private function importCsv(ImportDefinition $definition, string $csv, ?Model $scope, User $actor): array
    {
        return app(ImportRunner::class)->import($definition, $this->file($csv), $scope, $actor);
    }

    private function file(string $csv): string
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.csv';
        file_put_contents($path, $csv);

        return $path;
    }

    /*
    |--------------------------------------------------------------------------
    | People
    |--------------------------------------------------------------------------
    */

    public function test_people_are_created_with_their_role_and_no_usable_password(): void
    {
        Notification::fake();
        $admin = $this->userWithRole(Role::SuperAdmin->value);

        $this->importCsv(new UserImport, "name,email,role,title\n"
            ."Chinelo Okafor,chinelo@uprl.test,student,\n"
            ."Adaeze Nwosu,adaeze@uprl.test,instructor,Prof\n", null, $admin);

        $student = User::where('email', 'chinelo@uprl.test')->firstOrFail();
        $instructor = User::where('email', 'adaeze@uprl.test')->firstOrFail();

        $this->assertTrue($student->hasRole(Role::Student->value));
        $this->assertTrue($instructor->hasRole(Role::Instructor->value));
        $this->assertSame('Prof', $instructor->title);

        // No password came from the file, so none can be guessed from it.
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('password', $student->password));
    }

    public function test_an_existing_email_is_skipped_never_overwritten(): void
    {
        Notification::fake();
        $admin = $this->userWithRole(Role::SuperAdmin->value);
        $existing = User::factory()->create(['email' => 'taken@uprl.test', 'name' => 'Original Name']);

        $report = $this->analyse(new UserImport, "name,email,role\nImposter,taken@uprl.test,student\n", null, $admin);

        $this->assertSame(UserImport::EXISTS, $report['rows'][0]->problem);
        $this->assertSame('Original Name', $existing->refresh()->name);
    }

    public function test_a_duplicate_email_within_the_file_imports_once(): void
    {
        Notification::fake();
        $admin = $this->userWithRole(Role::SuperAdmin->value);

        $result = $this->importCsv(new UserImport, "name,email,role\n"
            ."First,dup@uprl.test,student\n"
            ."Second,dup@uprl.test,student\n", null, $admin);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, User::where('email', 'dup@uprl.test')->count());
    }

    public function test_role_synonyms_resolve(): void
    {
        $admin = $this->userWithRole(Role::SuperAdmin->value);

        $report = $this->analyse(new UserImport, "name,email,role\n"
            ."A,a@uprl.test,Lecturer\n"
            ."B,b@uprl.test,LEARNER\n"
            ."C,c@uprl.test,wizard\n", null, $admin);

        $this->assertTrue($report['rows'][0]->isOk());
        $this->assertTrue($report['rows'][1]->isOk());
        $this->assertSame(UserImport::UNKNOWN_ROLE, $report['rows'][2]->problem);
    }

    public function test_a_malformed_email_is_refused(): void
    {
        $admin = $this->userWithRole(Role::SuperAdmin->value);

        $report = $this->analyse(new UserImport, "name,email,role\nAda,not-an-email,student\n", null, $admin);

        $this->assertSame(UserImport::BAD_EMAIL, $report['rows'][0]->problem);
    }

    /*
    |--------------------------------------------------------------------------
    | Courses
    |--------------------------------------------------------------------------
    */

    public function test_courses_arrive_as_drafts_placed_in_their_programme_part(): void
    {
        $admin = $this->userWithRole(Role::SuperAdmin->value);
        $programme = Programme::factory()->create(['name' => 'Certificate in Public Relations', 'code' => 'CPR']);
        $part = ProgrammePart::factory()->create(['programme_id' => $programme->id, 'name' => 'Part I', 'slug' => 'part-i']);

        $this->importCsv(new CourseImport, "code,title,credits,programme,part,requirement\n"
            ."CPR112,Principles of Public Relations,3,CPR,Part I,compulsory\n", null, $admin);

        $course = Course::where('code', 'CPR112')->firstOrFail();

        $this->assertSame(CourseStatus::Draft, $course->status, 'a bulk-created course must never arrive published');
        $this->assertTrue($course->programmeParts->contains($part));
        $this->assertSame(3, (int) $course->programmeParts->first()->pivot->credit_load);
        $this->assertTrue((bool) $course->programmeParts->first()->pivot->is_primary);
    }

    public function test_a_part_is_matched_across_roman_and_arabic_numbering(): void
    {
        $admin = $this->userWithRole(Role::SuperAdmin->value);
        $programme = Programme::factory()->create(['name' => 'New Programme', 'code' => 'NPV']);
        ProgrammePart::factory()->create(['programme_id' => $programme->id, 'name' => 'Part 1', 'slug' => 'part-1']);

        // The sheet says "Part I"; the programme calls it "Part 1".
        $report = $this->analyse(new CourseImport, "code,title,programme,part\nNPV101,Intro,NPV,Part I\n", null, $admin);

        $this->assertTrue($report['rows'][0]->isOk());
    }

    public function test_a_mistyped_programme_is_refused_rather_than_created_unplaced(): void
    {
        $admin = $this->userWithRole(Role::SuperAdmin->value);

        $report = $this->analyse(new CourseImport, "code,title,programme,part\nXYZ101,Intro,NOPE,Part I\n", null, $admin);

        $this->assertSame(CourseImport::UNKNOWN_PROGRAMME, $report['rows'][0]->problem);
    }

    public function test_an_existing_course_code_is_never_overwritten(): void
    {
        $admin = $this->userWithRole(Role::SuperAdmin->value);
        Course::factory()->create(['code' => 'CPR112', 'title' => 'The Real One']);

        $report = $this->analyse(new CourseImport, "code,title\nCPR112,A Different Title\n", null, $admin);

        $this->assertSame(CourseImport::EXISTS, $report['rows'][0]->problem);
        $this->assertSame('The Real One', Course::where('code', 'CPR112')->first()->title);
    }

    public function test_two_courses_with_the_same_title_get_distinct_slugs(): void
    {
        $admin = $this->userWithRole(Role::SuperAdmin->value);

        $this->importCsv(new CourseImport, "code,title\nAAA101,Communication\nBBB202,Communication\n", null, $admin);

        $slugs = Course::whereIn('code', ['AAA101', 'BBB202'])->pluck('slug');

        $this->assertCount(2, $slugs->unique(), 'slugs are public URLs and must not collide');
    }

    /*
    |--------------------------------------------------------------------------
    | Grades
    |--------------------------------------------------------------------------
    */

    private function gradableAssignment(): array
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->published()->create(['created_by' => $instructor->id]);
        $assignment = Assignment::factory()->create([
            'course_id' => $course->id,
            'created_by' => $instructor->id,
            'max_points' => 100,
            'rubric_id' => null,
        ]);

        $student = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->active()->create(['user_id' => $student->id, 'course_id' => $course->id]);
        $submission = Submission::factory()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'version' => 1,
        ]);

        return [$assignment, $instructor, $student, $submission];
    }

    public function test_marks_are_recorded_against_the_students_submission(): void
    {
        Notification::fake();
        [$assignment, $instructor, $student, $submission] = $this->gradableAssignment();

        $this->importCsv(
            app(GradeImport::class),
            "email,score,feedback\n{$student->email},72,Strong analysis.\n",
            $assignment,
            $instructor,
        );

        $this->assertDatabaseHas('grades', [
            'submission_id' => $submission->id,
            'points_total' => 72,
            'feedback' => 'Strong analysis.',
        ]);
    }

    public function test_a_score_above_the_maximum_is_refused_not_clamped(): void
    {
        [$assignment, $instructor, $student] = $this->gradableAssignment();

        $report = $this->analyse(
            app(GradeImport::class),
            "email,score\n{$student->email},150\n",
            $assignment,
            $instructor,
        );

        $this->assertSame(GradeImport::OVER_MAX, $report['rows'][0]->problem);
        $this->assertDatabaseCount('grades', 0);
    }

    public function test_a_student_who_never_submitted_is_flagged_not_graded(): void
    {
        [$assignment, $instructor] = $this->gradableAssignment();
        $absentee = $this->userWithRole(Role::Student->value);

        $report = $this->analyse(
            app(GradeImport::class),
            "email,score\n{$absentee->email},50\n",
            $assignment,
            $instructor,
        );

        $this->assertSame(GradeImport::NO_SUBMISSION, $report['rows'][0]->problem);
    }

    public function test_a_rubric_graded_assignment_refuses_the_whole_file(): void
    {
        [$assignment, $instructor, $student] = $this->gradableAssignment();

        $rubric = \App\Models\Rubric::factory()->create(['created_by' => $instructor->id]);
        \App\Models\RubricCriterion::factory()->create(['rubric_id' => $rubric->id]);
        $assignment->forceFill(['rubric_id' => $rubric->id])->save();

        $this->expectException(ImportFormatException::class);
        $this->expectExceptionMessageMatches('/rubric/');

        $this->analyse(
            app(GradeImport::class),
            "email,score\n{$student->email},50\n",
            $assignment->refresh(),
            $instructor,
        );
    }

    public function test_only_someone_who_can_grade_the_assignment_may_upload_marks(): void
    {
        [$assignment] = $this->gradableAssignment();
        $stranger = $this->userWithRole(Role::Student->value);

        $this->actingAs($stranger)
            ->get(route('admin.imports.create', ['import' => 'grades', 'scopeId' => $assignment->id]))
            ->assertForbidden();
    }
}
