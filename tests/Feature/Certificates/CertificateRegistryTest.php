<?php

namespace Tests\Feature\Certificates;

use App\Enums\Role;
use App\Models\Certificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin certificate registry: revoke/restore mutate correctly and are gated to admins
 * (certificates.manage); the read-only auditor can browse (certificates.view) but never
 * mutate; a plain student can't reach the admin area at all.
 */
class CertificateRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_revoke_and_restore_a_certificate(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $certificate = Certificate::factory()->create();

        $revoke = $this->actingAs($admin)->post(route('admin.certificates.revoke', $certificate), [
            'reason' => 'Academic integrity investigation.',
        ]);
        $revoke->assertRedirect();
        $certificate->refresh();
        $this->assertTrue($certificate->isRevoked());
        $this->assertSame('Academic integrity investigation.', $certificate->revocation_reason);

        $restore = $this->actingAs($admin)->post(route('admin.certificates.restore', $certificate));
        $restore->assertRedirect();
        $this->assertFalse($certificate->refresh()->isRevoked());
        $this->assertNull($certificate->revocation_reason);
    }

    public function test_revoke_requires_a_reason(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $certificate = Certificate::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.certificates.revoke', $certificate), ['reason' => '']);

        $response->assertSessionHasErrors('reason');
        $this->assertFalse($certificate->refresh()->isRevoked());
    }

    public function test_auditor_can_view_the_registry_but_cannot_revoke(): void
    {
        $auditor = $this->userWithRole(Role::Auditor->value);
        $certificate = Certificate::factory()->create();

        $this->actingAs($auditor)->get(route('admin.certificates.index'))->assertOk();

        $response = $this->actingAs($auditor)->post(route('admin.certificates.revoke', $certificate), [
            'reason' => 'Trying anyway.',
        ]);

        $response->assertForbidden();
        $this->assertFalse($certificate->refresh()->isRevoked());
    }

    public function test_a_student_cannot_reach_the_admin_registry(): void
    {
        $student = $this->userWithRole(Role::Student->value);

        $this->actingAs($student)->get(route('admin.certificates.index'))->assertForbidden();
    }
}
