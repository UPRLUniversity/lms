<?php

namespace Tests\Feature\Courses;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Jobs\ProcessEnrollmentImport;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\Courses\BulkEnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BulkEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private function service(): BulkEnrollmentService
    {
        return app(BulkEnrollmentService::class);
    }

    public function test_analyze_flags_every_kind_of_problem_precisely(): void
    {
        $student = $this->userWithRole(Role::Student->value, ['email' => 'good@uprl.test']);
        $already = $this->userWithRole(Role::Student->value, ['email' => 'already@uprl.test']);
        $course = Course::factory()->published()->create(['code' => 'ABC101']);

        Enrollment::factory()->active()->create(['user_id' => $already->id, 'course_id' => $course->id]);

        $csv = implode("\n", [
            'email,course_code',
            'good@uprl.test,ABC101',        // ok
            'good@uprl.test,ABC101',        // duplicate (same pair, second time)
            'already@uprl.test,ABC101',     // already enrolled
            'ghost@uprl.test,ABC101',       // unknown email
            'good@uprl.test,ZZZ999',        // unknown code
        ]);

        $report = $this->service()->analyze($csv);
        $problems = collect($report['rows'])->pluck('problem')->all();

        $this->assertSame([
            BulkEnrollmentService::OK,
            BulkEnrollmentService::DUPLICATE,
            BulkEnrollmentService::ALREADY_ENROLLED,
            BulkEnrollmentService::UNKNOWN_EMAIL,
            BulkEnrollmentService::UNKNOWN_CODE,
        ], $problems);

        $this->assertSame(5, $report['counts']['total']);
        $this->assertSame(1, $report['counts']['valid']);
        $this->assertSame(4, $report['counts']['invalid']);
    }

    public function test_import_enrols_only_the_valid_rows(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $student = $this->userWithRole(Role::Student->value, ['email' => 'good@uprl.test']);
        $course = Course::factory()->published()->create(['code' => 'ABC101']);

        $csv = implode("\n", [
            'email,course_code',
            'good@uprl.test,ABC101',     // ok
            'ghost@uprl.test,ABC101',    // unknown email — skipped
        ]);

        $result = $this->service()->import($csv, $admin);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, $result['skipped']);
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active->value,
            'source' => 'bulk',
        ]);
    }

    public function test_preview_endpoint_renders_the_report(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $this->userWithRole(Role::Student->value, ['email' => 'good@uprl.test']);
        Course::factory()->published()->create(['code' => 'ABC101']);

        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent('roster.csv', "email,course_code\ngood@uprl.test,ABC101\n");

        $this->actingAs($admin)
            ->post(route('enrollments.bulk.preview'), ['file' => $file])
            ->assertOk()
            ->assertSee('Ready to import')
            ->assertSee('good@uprl.test');
    }

    public function test_small_import_runs_inline_and_creates_enrolments(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $student = $this->userWithRole(Role::Student->value, ['email' => 'good@uprl.test']);
        $course = Course::factory()->published()->create(['code' => 'ABC101']);

        Storage::fake('local');
        $token = (string) Str::uuid();
        Storage::disk('local')->put("enrollment-imports/{$token}.csv", "email,course_code\ngood@uprl.test,ABC101\n");

        $this->actingAs($admin)
            ->post(route('enrollments.bulk.store'), ['token' => $token])
            ->assertRedirect(route('enrollments.bulk.create'));

        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => $course->id]);
        // The staged file is cleaned up after an inline import.
        Storage::disk('local')->assertMissing("enrollment-imports/{$token}.csv");
    }

    public function test_large_import_is_queued(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        Queue::fake();
        Storage::fake('local');

        // 101 rows → over the inline threshold.
        $lines = ['email,course_code'];
        for ($i = 1; $i <= 101; $i++) {
            $lines[] = "student{$i}@uprl.test,ABC101";
        }
        $token = (string) Str::uuid();
        Storage::disk('local')->put("enrollment-imports/{$token}.csv", implode("\n", $lines));

        $this->actingAs($admin)
            ->post(route('enrollments.bulk.store'), ['token' => $token])
            ->assertRedirect();

        Queue::assertPushed(ProcessEnrollmentImport::class);
    }

    public function test_template_can_be_downloaded(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $response = $this->actingAs($admin)->get(route('enrollments.bulk.template'));
        $response->assertOk();
        $this->assertStringContainsString('email,course_code', $response->streamedContent());
    }

    public function test_non_admin_cannot_reach_bulk_import(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);

        $this->actingAs($instructor)->get(route('enrollments.bulk.create'))->assertForbidden();
    }
}
