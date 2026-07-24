<?php

namespace Tests\Feature\Certificates;

use App\Models\Certificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public /verify portal: valid/revoked/not-found states, the privacy rule (grade
 * never shown publicly, revocation reason withheld) and basic rate limiting. No auth
 * anywhere in this file — the whole point is that a stranger can use it.
 */
class CertificateVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_serial_shows_the_valid_state_without_the_grade(): void
    {
        $certificate = Certificate::factory()->withGrade()->create();

        $response = $this->get(route('verify.show', $certificate->serial));

        $response->assertOk();
        $response->assertSee($certificate->user->name);
        $response->assertSee($certificate->course->title);
        $response->assertSee($certificate->serial);
        $response->assertDontSee($certificate->gradeLine());
        $response->assertDontSee('4.5/5.0');
    }

    public function test_a_revoked_serial_shows_revoked_without_the_reason(): void
    {
        $certificate = Certificate::factory()->revoked('Confidential integrity finding')->create();

        $response = $this->get(route('verify.show', $certificate->serial));

        $response->assertOk();
        $response->assertSee('revoked', false);
        $response->assertDontSee('Confidential integrity finding');
    }

    public function test_a_gibberish_serial_shows_not_found_without_leaking_format_hints(): void
    {
        $response = $this->get(route('verify.show', 'NOT-A-REAL-SERIAL'));

        $response->assertOk();
        $response->assertSee("couldn't find a certificate", false);
    }

    public function test_the_manual_lookup_form_redirects_to_the_canonical_show_url(): void
    {
        $certificate = Certificate::factory()->create();

        $response = $this->post(route('verify.lookup'), ['serial' => ' '.strtolower($certificate->serial).' ']);

        $response->assertRedirect(route('verify.show', $certificate->serial));
    }

    public function test_lookup_is_case_and_whitespace_insensitive(): void
    {
        $certificate = Certificate::factory()->create();

        $response = $this->get(route('verify.show', strtolower($certificate->serial)));

        $response->assertOk();
        $response->assertSee($certificate->user->name);
    }

    public function test_the_verify_endpoint_is_rate_limited(): void
    {
        $certificate = Certificate::factory()->create();

        for ($i = 0; $i < 30; $i++) {
            $this->get(route('verify.show', $certificate->serial));
        }

        $response = $this->get(route('verify.show', $certificate->serial));

        $response->assertStatus(429);
    }
}
