<?php

namespace Tests\Feature;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Section 15 — localization groundwork and the final polish pass.
 */
class LocalizationAndPolishTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    */

    public function test_translation_files_exist_and_resolve(): void
    {
        foreach (['nav', 'auth', 'common', 'learn'] as $file) {
            $this->assertFileExists(lang_path("en/{$file}.php"));
        }

        $this->assertSame('Dashboard', __('nav.dashboard'));
        $this->assertSame('Save changes', __('common.save_changes'));
        $this->assertSame('Continue learning', __('learn.continue_learning'));

        // A missing key returns the key itself, which is how an untranslated string
        // is spotted on the page rather than rendering as blank.
        $this->assertSame('common.not_a_real_key', __('common.not_a_real_key'));
    }

    public function test_framework_auth_keys_are_preserved(): void
    {
        // Laravel looks these up by exact name; renaming them silently breaks the
        // "these credentials do not match" message.
        $this->assertSame('These credentials do not match our records.', __('auth.failed'));
        $this->assertStringContainsString(':seconds', __('auth.throttle'));
    }

    public function test_the_locale_switcher_is_hidden_until_the_setting_enables_it(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('nav.choose_language');
        $this->get(route('home'))->assertDontSee('Choose a language');
    }

    public function test_an_unoffered_locale_is_rejected(): void
    {
        // The value reaches App::setLocale and becomes part of a translation file path,
        // so anything not explicitly offered must 404 rather than be set.
        $this->post(route('locale.update', 'fr'))->assertNotFound();
        $this->post(route('locale.update', '../../etc/passwd'))->assertNotFound();

        $this->assertNull(session('locale'));
    }

    public function test_an_offered_locale_is_accepted_and_remembered(): void
    {
        $this->from(route('home'))
            ->post(route('locale.update', 'en'))
            ->assertRedirect(route('home'));

        $this->assertSame('en', session('locale'));
    }

    /*
    |--------------------------------------------------------------------------
    | Final polish
    |--------------------------------------------------------------------------
    */

    public function test_the_500_page_renders_with_the_brand_and_support_address(): void
    {
        // Rendered directly: triggering a real 500 in a test would be caught by the
        // exception handler and reported instead.
        $html = view('errors.500')->render();

        $this->assertStringContainsString('500', $html);
        $this->assertStringContainsString('Something went wrong on our side', $html);
        $this->assertStringContainsString(config('mail.support'), $html);
        $this->assertStringContainsString(config('brand.motto'), $html);
    }

    public function test_the_503_page_renders(): void
    {
        $html = view('errors.503')->render();

        $this->assertStringContainsString('503', $html);
        $this->assertStringContainsString('Down for maintenance', $html);
    }

    public function test_terms_and_privacy_are_reachable_by_a_guest(): void
    {
        // A visitor must be able to read the terms BEFORE creating an account.
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('Terms of use')
            ->assertSee('Assessment and academic honesty');

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Privacy policy')
            ->assertSee('What we collect');
    }

    public function test_legal_pages_are_honest_about_being_placeholders(): void
    {
        // Placeholder wording must not be able to pass itself off as a binding policy.
        $this->get(route('legal.terms'))->assertOk()->assertSee('Placeholder');
        $this->get(route('legal.privacy'))->assertOk()->assertSee('Placeholder');
    }

    public function test_both_footers_link_to_the_legal_pages(): void
    {
        // Public site footer.
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('legal.terms'), escape: false)
            ->assertSee(route('legal.privacy'), escape: false);

        // App shell footer.
        $user = $this->userWithRole(Role::Student->value);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('legal.terms'), escape: false)
            ->assertSee(route('legal.privacy'), escape: false);
    }

    public function test_admin_pages_carry_breadcrumbs(): void
    {
        $superAdmin = $this->userWithRole(Role::SuperAdmin->value);

        $this->actingAs($superAdmin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('aria-label="Breadcrumb"', escape: false)
            ->assertSee('aria-current="page"', escape: false);
    }

    public function test_pages_have_distinct_titles(): void
    {
        $superAdmin = $this->userWithRole(Role::SuperAdmin->value);

        $this->actingAs($superAdmin)
            ->get(route('admin.settings.index', 'commerce'))
            ->assertOk()
            ->assertSee('<title>Settings · Commerce · '.config('brand.name').'</title>', escape: false);

        $this->actingAs($superAdmin)
            ->get(route('admin.audit.index'))
            ->assertOk()
            ->assertSee('<title>Audit trail · '.config('brand.name').'</title>', escape: false);
    }

    public function test_the_favicon_is_wired_from_settings_not_hard_coded(): void
    {
        // With nothing uploaded the packaged icon is used; the point is that the tag
        // is produced by BrandAssets rather than a literal path in the layout.
        $partial = file_get_contents(resource_path('views/layouts/partials/favicons.blade.php'));

        $this->assertStringContainsString('brand_assets()', $partial);
        $this->assertStringNotContainsString("asset(config('brand.icons", $partial);
    }

    public function test_every_named_get_route_in_the_admin_area_resolves(): void
    {
        // A cheap guard against a renamed route leaving a dead link in the sidebar.
        foreach (config('navigation') as $item) {
            if ($item['route'] === null) {
                continue;
            }

            $this->assertTrue(
                Route::has($item['route']),
                "Sidebar item “{$item['label']}” points at a route that does not exist: {$item['route']}",
            );
        }
    }
}
