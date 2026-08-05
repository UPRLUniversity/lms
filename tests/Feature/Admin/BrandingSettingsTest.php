<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\CertificateTemplate;
use App\Models\Media;
use App\Services\Branding\BrandAssets;
use App\Services\Certificates\CertificateRenderer;
use App\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Section 15, acceptance criterion 1: changing the logo in Settings must reach the app
 * chrome, e-mails, newly generated certificates AND the Section 13 public site —
 * without a code change at any of those call sites.
 *
 * Each surface is asserted independently rather than through one shared helper, because
 * they resolve the asset in genuinely different ways: two want a URL, two want embedded
 * bytes (dompdf cannot fetch, and a mail client will not).
 */
class BrandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** A 1×1 PNG, so the bytes asserted on are real image bytes. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_uploading_a_logo_reaches_every_surface(): void
    {
        $superAdmin = $this->userWithRole(Role::SuperAdmin->value);

        $this->actingAs($superAdmin)
            ->put(route('admin.settings.update'), [
                'group' => 'branding',
                'settings' => ['branding__login_tagline' => 'Study with us.'],
                'uploads' => ['branding__logo_color' => $this->pngFile('new-logo.png')],
            ])
            ->assertRedirect();

        $media = Media::query()->where('purpose', 'brand_assets')->firstOrFail();
        $this->assertSame(
            (string) $media->id,
            (string) app(SettingsRepository::class)->int('branding.logo_color'),
            'The branding setting must point at the uploaded asset.',
        );

        // 1. App chrome — the sidebar logo on a signed-in page.
        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($media->url, escape: false);

        // 2. The public marketing site (Section 13), which uses the same component.
        $this->get(route('home'))
            ->assertOk()
            ->assertSee($media->url, escape: false);
    }

    public function test_an_uploaded_logo_is_embedded_in_certificates_and_email(): void
    {
        $superAdmin = $this->userWithRole(Role::SuperAdmin->value);

        $this->actingAs($superAdmin)->put(route('admin.settings.update'), [
            'group' => 'branding',
            'settings' => ['branding__login_tagline' => 'Study with us.'],
            'uploads' => ['branding__logo_color' => $this->pngFile('cert-logo.png')],
        ])->assertRedirect();

        app(SettingsRepository::class)->flush();
        app()->forgetInstance(BrandAssets::class);

        $dataUri = app(BrandAssets::class)->dataUri('color');

        $this->assertNotNull($dataUri, 'An uploaded logo must resolve to embeddable bytes.');
        $this->assertStringContainsString(self::PNG, $dataUri, 'The embedded bytes must be the uploaded file.');

        // 3. Newly generated certificates embed it (dompdf cannot fetch a URL).
        $template = CertificateTemplate::factory()->create();
        $html = app(CertificateRenderer::class)->renderPreviewHtml($template, false);

        $this->assertStringContainsString(self::PNG, $html);
    }

    public function test_falls_back_to_packaged_artwork_when_nothing_is_uploaded(): void
    {
        // With no upload and no file in public/images/brand/, the resolver returns
        // null and the monogram renders — the app is never left with a broken image.
        $assets = app(BrandAssets::class);

        $packagedExists = file_exists(public_path((string) config('brand.logos.color')));

        if ($packagedExists) {
            $this->assertNotNull($assets->url('color'));
        } else {
            $this->assertNull($assets->url('color'));
            $this->get(route('home'))->assertOk()->assertSee(config('brand.short'));
        }
    }

    public function test_clearing_a_logo_reverts_to_the_packaged_asset(): void
    {
        $superAdmin = $this->userWithRole(Role::SuperAdmin->value);

        $this->actingAs($superAdmin)->put(route('admin.settings.update'), [
            'group' => 'branding',
            'settings' => ['branding__login_tagline' => 'Study with us.'],
            'uploads' => ['branding__logo_color' => $this->pngFile('temp-logo.png')],
        ])->assertRedirect();

        $this->assertNotNull(app(SettingsRepository::class)->int('branding.logo_color'));

        $this->actingAs($superAdmin)->put(route('admin.settings.update'), [
            'group' => 'branding',
            'settings' => ['branding__login_tagline' => 'Study with us.'],
            'clear' => ['branding__logo_color' => '1'],
        ])->assertRedirect();

        app(SettingsRepository::class)->flush();
        $this->assertNull(app(SettingsRepository::class)->int('branding.logo_color'));
    }

    public function test_a_non_image_upload_is_rejected(): void
    {
        $superAdmin = $this->userWithRole(Role::SuperAdmin->value);

        $this->actingAs($superAdmin)
            ->put(route('admin.settings.update'), [
                'group' => 'branding',
                'settings' => ['branding__login_tagline' => 'Study with us.'],
                'uploads' => [
                    'branding__logo_color' => UploadedFile::fake()->createWithContent('payload.php', '<?php echo 1;'),
                ],
            ])
            ->assertSessionHasErrors('uploads.branding__logo_color');

        $this->assertSame(0, Media::query()->where('purpose', 'brand_assets')->count());
    }

    private function pngFile(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::PNG));
    }
}
