<?php

namespace Tests\Feature\Notifications;

use App\Enums\Role;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Notifications\CertificateIssuedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CertificateNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_issuance_notifies_the_student(): void
    {
        Notification::fake();

        CertificateTemplate::factory()->default()->create();
        $admin = $this->userWithRole(Role::Admin->value);
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();
        Enrollment::factory()->completed()->create(['user_id' => $student->id, 'course_id' => $course->id]);

        $this->actingAs($admin)->post(route('admin.certificates.issue'), [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);

        Notification::assertSentTo($student, CertificateIssuedNotification::class);
    }
}
