<?php

namespace Tests\Feature\Certificates;

use App\Enums\MediaPurpose;
use App\Enums\Role;
use App\Models\Certificate;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Student-facing certificate access: My Certificates lists only the signed-in user's
 * own certificates, and the download route is owner-gated — one student can never
 * fetch another's PDF just by guessing/incrementing an id (it's a ULID anyway, but the
 * policy check is the real guard).
 */
class CertificateAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_certificates_lists_only_the_signed_in_users_own_certificates(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $mine = Certificate::factory()->create(['user_id' => $student->id]);
        $someoneElses = Certificate::factory()->create();

        $response = $this->actingAs($student)->get(route('certificates.mine'));

        $response->assertOk();
        $response->assertSee($mine->course->title);
        $response->assertDontSee($someoneElses->course->title);
    }

    public function test_a_student_can_download_their_own_certificate(): void
    {
        Storage::fake('private');

        $student = $this->userWithRole(Role::Student->value);
        $certificate = Certificate::factory()->create(['user_id' => $student->id]);
        Storage::disk('private')->put('certificates/'.$certificate->serial.'.pdf', '%PDF-1.4 fake');
        Media::factory()->private()->create([
            'mediable_type' => Certificate::class,
            'mediable_id' => $certificate->id,
            'purpose' => MediaPurpose::Certificates,
            'path' => 'certificates/'.$certificate->serial.'.pdf',
            'original_name' => $certificate->serial.'.pdf',
        ]);

        $response = $this->actingAs($student)->get(route('certificates.download', $certificate));

        $response->assertOk();
    }

    public function test_a_student_cannot_download_someone_elses_certificate(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $certificate = Certificate::factory()->create();

        $response = $this->actingAs($student)->get(route('certificates.download', $certificate));

        $response->assertForbidden();
    }

    public function test_the_status_endpoint_reports_readiness(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $certificate = Certificate::factory()->create(['user_id' => $student->id, 'rendered_at' => null]);

        $response = $this->actingAs($student)->getJson(route('certificates.status', $certificate));

        $response->assertOk()->assertJson(['ready' => false]);
    }
}
