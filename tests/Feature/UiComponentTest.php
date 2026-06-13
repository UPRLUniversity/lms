<?php

namespace Tests\Feature;

use Tests\TestCase;

class UiComponentTest extends TestCase
{
    public function test_button_renders_as_anchor_when_href_is_given(): void
    {
        $view = $this->blade('<x-ui.button href="/go">Continue</x-ui.button>');

        $view->assertSee('<a', false);
        $view->assertSee('href="/go"', false);
        $view->assertSee('Continue');
    }

    public function test_button_renders_as_button_with_type(): void
    {
        $view = $this->blade('<x-ui.button type="submit">Save</x-ui.button>');

        $view->assertSee('<button', false);
        $view->assertSee('type="submit"', false);
    }

    public function test_field_wires_label_to_input(): void
    {
        $view = $this->blade('<x-ui.field name="email" label="Email address" />');

        $view->assertSee('for="email"', false);
        $view->assertSee('id="email"', false);
        $view->assertSee('Email address');
    }

    public function test_field_shows_error_and_aria_wiring_when_invalid(): void
    {
        $view = $this->withViewErrors(['email' => 'That email looks wrong.'])
            ->blade('<x-ui.field name="email" label="Email" />');

        $view->assertSee('That email looks wrong.');
        $view->assertSee('aria-describedby="email-error"', false);
        $view->assertSee('aria-invalid="true"', false);
        $view->assertSee('id="email-error"', false);
    }
}
