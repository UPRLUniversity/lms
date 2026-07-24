<?php

namespace Tests\Feature\Certificates;

use App\Enums\Role;
use App\Models\CertificateTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Certificate template admin: exactly one default at a time (mirrors GradeScaleService's
 * invariant), the auditor has no access (template design isn't part of its read-only
 * record-keeping view), and a plain student can't reach it either.
 */
class CertificateTemplateAdminTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Classic',
            'layout' => 'classic',
            'is_default' => '1',
            'show_grade' => '1',
            'accent_color' => '',
            'signatory_one' => ['name' => 'Prof. Adaeze Nwosu', 'title' => 'Vice-Chancellor', 'signature_media_id' => ''],
            'signatory_two' => ['name' => '', 'title' => '', 'signature_media_id' => ''],
        ], $overrides);
    }

    public function test_creating_a_second_default_unsets_the_first(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $first = CertificateTemplate::factory()->default()->create();

        $response = $this->actingAs($admin)->post(route('admin.certificate-templates.store'), $this->payload(['name' => 'Modern', 'layout' => 'modern']));

        $response->assertRedirect();
        $this->assertFalse($first->refresh()->is_default);
        $this->assertTrue(CertificateTemplate::where('name', 'Modern')->first()->is_default);
        $this->assertSame(1, CertificateTemplate::where('is_default', true)->count());
    }

    public function test_unchecking_the_only_default_is_a_no_op(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $template = CertificateTemplate::factory()->default()->create();

        $this->actingAs($admin)->put(route('admin.certificate-templates.update', $template), $this->payload(['is_default' => '0']));

        $this->assertTrue($template->refresh()->is_default, 'the sole default cannot be unchecked without promoting another');
    }

    public function test_auditor_cannot_manage_templates(): void
    {
        $auditor = $this->userWithRole(Role::Auditor->value);

        $this->actingAs($auditor)->get(route('admin.certificate-templates.index'))->assertForbidden();
    }

    public function test_student_cannot_manage_templates(): void
    {
        $student = $this->userWithRole(Role::Student->value);

        $this->actingAs($student)->get(route('admin.certificate-templates.index'))->assertForbidden();
    }
}
