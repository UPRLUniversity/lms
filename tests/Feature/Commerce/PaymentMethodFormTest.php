<?php

namespace Tests\Feature\Commerce;

use App\Enums\Role;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The payment-methods screen edits the most dangerous configuration in the app, and it
 * failed in a way no server-side test could see: the Remove form was nested inside the
 * update form. Browsers flatten nested forms, which left a second `_method` — value
 * DELETE — after the PUT. PHP keeps the LAST duplicate, so pressing "Save changes"
 * submitted a DELETE: the method was destroyed, silently reinstalled with blank
 * credentials, and then refused to switch on for want of the keys just entered.
 *
 * These assertions are about the rendered markup because that is where the defect lived.
 */
class PaymentMethodFormTest extends TestCase
{
    use RefreshDatabase;

    private function paystack(): PaymentMethod
    {
        return PaymentMethod::factory()->create([
            'key' => 'paystack',
            'label' => 'Paystack',
            'is_enabled' => false,
            'config' => ['public_key' => '', 'secret_key' => ''],
        ]);
    }

    /**
     * The update form's markup, from <form …update…> to its </form>.
     */
    private function updateFormHtml(string $html): string
    {
        $start = strpos($html, 'action="'.route('admin.payment-methods.update', 'paystack').'"');
        $this->assertNotFalse($start, 'the update form must be on the page');

        $open = strrpos(substr($html, 0, $start), '<form');
        $close = strpos($html, '</form>', $start);

        return substr($html, $open, $close - $open);
    }

    public function test_the_update_form_does_not_contain_a_nested_form_or_a_second_method_field(): void
    {
        $this->paystack();
        $admin = $this->userWithRole(Role::Admin->value);

        $html = $this->actingAs($admin)->get(route('admin.payment-methods.index'))->assertOk()->getContent();
        $form = $this->updateFormHtml($html);

        // From offset 1, so the form's own opening tag is not counted.
        $this->assertFalse(strpos($form, '<form', 1), 'a nested <form> gets flattened by the browser');
        $this->assertSame(1, substr_count($form, 'name="_method"'), 'a second _method would override the PUT');
        $this->assertStringNotContainsString('DELETE', $form, 'the delete form must live outside this one');
    }

    public function test_saving_credentials_persists_them_and_makes_the_method_configurable(): void
    {
        $method = $this->paystack();
        $admin = $this->userWithRole(Role::Admin->value);

        $this->actingAs($admin)->put(route('admin.payment-methods.update', $method), [
            'label' => 'Paystack',
            'environment' => 'test',
            'config' => ['public_key' => 'pk_test_ABC', 'secret_key' => 'sk_test_XYZ'],
        ])->assertRedirect();

        $method->refresh();
        $this->assertSame('sk_test_XYZ', $method->setting('secret_key'));
        $this->assertTrue($method->isConfigured());

        // And it can now actually be switched on — the step that was failing.
        $this->actingAs($admin)->post(route('admin.payment-methods.toggle', $method), ['enable' => 1])
            ->assertRedirect();

        $this->assertTrue($method->fresh()->is_enabled);
    }

    public function test_a_blank_secret_keeps_the_stored_one(): void
    {
        $method = $this->paystack();
        $method->update(['config' => ['public_key' => 'pk_test_ABC', 'secret_key' => 'sk_test_XYZ']]);
        $admin = $this->userWithRole(Role::Admin->value);

        // Editing only the environment must not wipe a key the form cannot display.
        $this->actingAs($admin)->put(route('admin.payment-methods.update', $method), [
            'label' => 'Paystack',
            'environment' => 'live',
            'config' => ['public_key' => 'pk_test_ABC', 'secret_key' => ''],
        ])->assertRedirect();

        $this->assertSame('sk_test_XYZ', $method->fresh()->setting('secret_key'));
    }

    public function test_credential_fields_opt_out_of_password_manager_autofill(): void
    {
        $this->paystack();
        $admin = $this->userWithRole(Role::Admin->value);

        $html = $this->actingAs($admin)->get(route('admin.payment-methods.index'))->assertOk()->getContent();
        $form = $this->updateFormHtml($html);

        // Chrome fills the text input above a password input with the signed-in address,
        // which silently replaced a pasted public key. autocomplete="off" is ignored here,
        // so BOTH credential inputs — not just the secret — must say "new-password".
        $this->assertSame(
            2,
            substr_count($form, 'autocomplete="new-password"'),
            'both credential fields must opt out, not just the secret',
        );

        foreach (['config[public_key]', 'config[secret_key]'] as $field) {
            $input = substr($form, strpos($form, 'name="'.$field.'"') - 400, 700);
            $this->assertStringContainsString('autocomplete="new-password"', $input, $field.' must opt out');
        }
    }

    public function test_the_enable_toggle_is_disabled_until_the_method_is_configured(): void
    {
        $method = $this->paystack();
        $admin = $this->userWithRole(Role::Admin->value);

        $html = $this->actingAs($admin)->get(route('admin.payment-methods.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Add its keys below', $html);

        $toggle = substr($html, strpos($html, route('admin.payment-methods.toggle', 'paystack')));
        $this->assertStringContainsString('disabled', substr($toggle, 0, 600));

        // Once configured the switch is live again.
        $method->update(['config' => ['public_key' => 'pk', 'secret_key' => 'sk_test_XYZ']]);

        $html = $this->actingAs($admin)->get(route('admin.payment-methods.index'))->assertOk()->getContent();
        $toggle = substr($html, strpos($html, route('admin.payment-methods.toggle', 'paystack')));
        $this->assertStringNotContainsString('disabled', substr($toggle, 0, 600));
    }
}
