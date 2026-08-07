<?php

namespace Tests\Feature\Communication;

use App\Enums\ConversationType;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Communication\MessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The composer's recipient list must agree with who the sender may actually message.
 * It didn't: canMessage let an admin reach anyone, while contactsFor only ever returned
 * coursemates — so /messages/create showed an admin "No one to message yet" while the
 * store endpoint would have accepted any of those same people. These tests pin the two
 * halves together.
 */
class AdminMessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_who_teaches_nothing_still_has_contacts_to_message(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $student = $this->userWithRole(Role::Student->value);
        $instructor = $this->userWithRole(Role::Instructor->value);

        $contacts = app(MessagingService::class)->contactsFor($admin);

        $this->assertTrue($contacts->contains('id', $student->id));
        $this->assertTrue($contacts->contains('id', $instructor->id));
        $this->assertFalse($contacts->contains('id', $admin->id), 'you cannot message yourself');
    }

    public function test_the_composer_offers_a_picker_rather_than_the_empty_state(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $student = $this->userWithRole(Role::Student->value);

        $this->actingAs($admin)
            ->get(route('messages.create'))
            ->assertOk()
            ->assertSee($student->name)
            ->assertDontSee('No one to message yet');
    }

    public function test_an_admin_can_message_a_student_they_share_no_course_with(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $student = $this->userWithRole(Role::Student->value);

        $this->actingAs($admin)->post(route('messages.store'), [
            'type' => ConversationType::Direct->value,
            'recipient_id' => $student->id,
            'body' => '<p>Please complete your registration.</p>',
        ])->assertRedirect();

        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_deactivated_accounts_are_not_offered_as_contacts(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $suspended = $this->userWithRole(Role::Student->value);
        $suspended->forceFill(['is_active' => false])->save();

        $contacts = app(MessagingService::class)->contactsFor($admin);

        $this->assertFalse($contacts->contains('id', $suspended->id));
    }

    public function test_a_students_contact_list_is_still_only_their_coursemates(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->published()->create(['created_by' => $instructor->id]);

        $student = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->active()->create(['user_id' => $student->id, 'course_id' => $course->id]);

        $stranger = $this->userWithRole(Role::Student->value);

        $contacts = app(MessagingService::class)->contactsFor($student);

        $this->assertTrue($contacts->contains('id', $instructor->id));
        $this->assertFalse($contacts->contains('id', $stranger->id), 'widening admin reach must not widen a student\'s');
    }

    public function test_contact_search_returns_matches_for_an_admin(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $student = $this->userWithRole(Role::Student->value);
        $student->forceFill(['name' => 'Chinelo Okafor'])->save();

        $response = $this->actingAs($admin)
            ->getJson(route('messages.contacts', ['q' => 'Chinelo']));

        $response->assertOk();
        $this->assertSame($student->id, $response->json('results.0.id'));
    }

    public function test_contact_search_cannot_be_used_to_scrape_the_directory(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $stranger = $this->userWithRole(Role::Student->value);
        $stranger->forceFill(['name' => 'Chinelo Okafor'])->save();

        $response = $this->actingAs($student)
            ->getJson(route('messages.contacts', ['q' => 'Chinelo']));

        $response->assertOk();
        $this->assertCount(0, $response->json('results'), 'a student shares no course with this person');
    }

    public function test_the_auditor_cannot_search_contacts(): void
    {
        $auditor = $this->userWithRole(Role::Auditor->value);

        $this->actingAs($auditor)
            ->getJson(route('messages.contacts', ['q' => 'a']))
            ->assertForbidden();
    }

    public function test_an_admin_can_start_a_thread_from_the_people_list(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $student = $this->userWithRole(Role::Student->value);

        $this->actingAs($admin)
            ->post(route('messages.start', $student))
            ->assertRedirect();

        $conversation = User::find($admin->id)->conversations()->first();
        $this->assertNotNull($conversation);
        $this->assertTrue($conversation->hasParticipant($student));
    }
}
