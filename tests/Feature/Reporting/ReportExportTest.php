<?php

namespace Tests\Feature\Reporting;

use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Jobs\GenerateReportExport;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GeneratedReport;
use App\Models\User;
use App\Notifications\ReportReadyNotification;
use App\Reports\LearnerReport;
use App\Services\Reporting\ReportExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithData(): User
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $course = Course::factory()->published()->create(['title' => 'Exportable Course']);
        $student = User::factory()->create(['name' => 'Zoe Exportee']);
        Enrollment::factory()->active()->create(['user_id' => $student->id, 'course_id' => $course->id]);

        return $admin;
    }

    public function test_csv_export_streams_with_headings_and_rows(): void
    {
        $this->freezeTime();
        Excel::fake();
        $admin = $this->adminWithData();

        $this->actingAs($admin)
            ->post(route('reports.export', 'learner'), ['format' => 'csv'])
            ->assertOk();

        Excel::assertDownloaded('learner-report-'.now()->format('Ymd').'-'.now()->format('His').'.csv', function ($export) {
            return in_array('Student', $export->headings(), true)
                && collect($export->array())->flatten()->contains('Zoe Exportee');
        });
    }

    public function test_xlsx_export_streams(): void
    {
        $this->freezeTime();
        Excel::fake();
        $admin = $this->adminWithData();

        $this->actingAs($admin)
            ->post(route('reports.export', 'learner'), ['format' => 'xlsx'])
            ->assertOk();

        Excel::assertDownloaded('learner-report-'.now()->format('Ymd').'-'.now()->format('His').'.xlsx');
    }

    public function test_pdf_export_returns_a_pdf(): void
    {
        $admin = $this->adminWithData();

        $response = $this->actingAs($admin)->post(route('reports.export', 'learner'), ['format' => 'pdf']);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_export_rejects_an_unknown_format(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $this->actingAs($admin)
            ->post(route('reports.export', 'learner'), ['format' => 'exe'])
            ->assertSessionHasErrors('format');
    }

    /*
    |--------------------------------------------------------------------------
    | Queued route for large exports (> 2k rows)
    |--------------------------------------------------------------------------
    */

    public function test_a_large_export_is_queued_rather_than_streamed(): void
    {
        Queue::fake();
        $admin = $this->userWithRole(Role::Admin->value);

        // Cheaply manufacture > QUEUE_THRESHOLD enrolment rows via bulk inserts.
        $course = Course::factory()->published()->create();
        $count = ReportExporter::QUEUE_THRESHOLD + 5;

        $now = now();
        $users = [];
        for ($i = 0; $i < $count; $i++) {
            $users[] = ['name' => "Bulk {$i}", 'email' => "bulk{$i}@uprl.test", 'password' => 'x', 'created_at' => $now, 'updated_at' => $now];
        }
        User::insert($users);
        $ids = User::where('email', 'like', 'bulk%@uprl.test')->pluck('id');

        $rows = $ids->map(fn ($id) => [
            'user_id' => $id, 'course_id' => $course->id, 'status' => 'active',
            'source' => 'self', 'enrolled_at' => $now, 'progress_percent' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ])->all();
        Enrollment::insert($rows);

        $response = $this->actingAs($admin)->post(route('reports.export', 'learner'), ['format' => 'csv']);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        Queue::assertPushed(GenerateReportExport::class);
        $this->assertDatabaseHas('generated_reports', [
            'user_id' => $admin->id, 'report' => 'learner', 'format' => 'csv', 'status' => ReportStatus::Pending->value,
        ]);
    }

    public function test_the_queued_job_builds_the_file_and_notifies_the_requester(): void
    {
        Storage::fake('private');
        Notification::fake();

        $admin = $this->adminWithData();
        $report = new LearnerReport;

        $generated = app(ReportExporter::class)->queue($report, [], 'xlsx', $admin->id, 1);
        (new GenerateReportExport($generated->id))->handle(app(\App\Reports\ReportRegistry::class), app(ReportExporter::class));

        $generated->refresh();
        $this->assertTrue($generated->status->isReady());
        $this->assertNotNull($generated->path);
        Storage::disk('private')->assertExists($generated->path);
        Notification::assertSentTo($admin, ReportReadyNotification::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Download is gated to the requester
    |--------------------------------------------------------------------------
    */

    public function test_only_the_owner_can_download_a_generated_report(): void
    {
        Storage::fake('private');

        $owner = $this->userWithRole(Role::Admin->value);
        $intruder = $this->userWithRole(Role::Admin->value);

        Storage::disk('private')->put('reports/demo.csv', 'Student,Email');
        $generated = GeneratedReport::create([
            'user_id' => $owner->id, 'report' => 'learner', 'format' => 'csv',
            'title' => 'Learner report', 'filename' => 'learner.csv', 'disk' => 'private',
            'path' => 'reports/demo.csv', 'row_count' => 1, 'filters' => [], 'status' => ReportStatus::Ready->value,
            'ready_at' => now(),
        ]);

        $this->actingAs($intruder)->get(route('reports.download', $generated))->assertForbidden();
        $this->actingAs($owner)->get(route('reports.download', $generated))->assertOk();
    }

    public function test_downloading_a_pending_report_404s(): void
    {
        $owner = $this->userWithRole(Role::Admin->value);
        $generated = GeneratedReport::create([
            'user_id' => $owner->id, 'report' => 'learner', 'format' => 'csv',
            'title' => 'Learner report', 'filename' => 'learner.csv', 'disk' => 'private',
            'path' => null, 'row_count' => 0, 'filters' => [], 'status' => ReportStatus::Pending->value,
        ]);

        $this->actingAs($owner)->get(route('reports.download', $generated))->assertNotFound();
    }
}
