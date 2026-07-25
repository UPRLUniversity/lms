<?php

namespace Tests\Feature\Notifications;

use App\Enums\Role;
use App\Models\Course;
use App\Notifications\BulkImportCompletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BulkImportNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_large_queued_import_notifies_the_admin_who_started_it(): void
    {
        Notification::fake();

        $admin = $this->userWithRole(Role::Admin->value);
        Course::factory()->published()->create(['code' => 'ABC101']);

        Storage::fake('local');
        $lines = ['email,course_code'];
        for ($i = 1; $i <= 101; $i++) {
            $lines[] = "ghost{$i}@uprl.test,ABC101"; // unknown emails — all skipped, still "completed"
        }
        $token = (string) Str::uuid();
        Storage::disk('local')->put("enrollment-imports/{$token}.csv", implode("\n", $lines));

        // QUEUE_CONNECTION=sync in tests, so the queued job runs inline here.
        $this->actingAs($admin)->post(route('enrollments.bulk.store'), ['token' => $token])->assertRedirect();

        Notification::assertSentTo($admin, BulkImportCompletedNotification::class, function ($notification) {
            return $notification->result['total'] === 101 && $notification->result['skipped'] === 101;
        });
    }
}
