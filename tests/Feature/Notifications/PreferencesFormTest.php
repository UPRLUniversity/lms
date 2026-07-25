<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationType;
use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreferencesFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_the_matrix_persists_per_type_channel_toggles_and_the_digest_flag(): void
    {
        $user = $this->userWithRole(Role::Student->value);

        $this->actingAs($user)->patch(route('profile.notifications.update'), [
            'email_digest' => '1',
            'email' => [NotificationType::AssignmentGraded->value => '1'],
            'in_app' => [NotificationType::AssignmentGraded->value => '1'],
            // CourseAnnouncement: both off.
        ])->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertTrue($user->wantsEmailDigest());
        $this->assertTrue($user->notifiesByEmail(NotificationType::AssignmentGraded));
        $this->assertFalse($user->notifiesByEmail(NotificationType::CourseAnnouncement));
        $this->assertFalse($user->notifiesInApp(NotificationType::CourseAnnouncement));
    }

    public function test_a_critical_types_preference_is_ignored_even_if_posted(): void
    {
        $user = $this->userWithRole(Role::Student->value);

        $this->actingAs($user)->patch(route('profile.notifications.update'), [
            'in_app' => [NotificationType::EnrollmentApproved->value => '0'],
        ]);

        $this->assertTrue($user->fresh()->notifiesInApp(NotificationType::EnrollmentApproved));
    }
}
