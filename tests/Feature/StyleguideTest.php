<?php

namespace Tests\Feature;

use Tests\TestCase;

class StyleguideTest extends TestCase
{
    public function test_styleguide_renders_in_local_testing_env(): void
    {
        $response = $this->get('/styleguide');

        $response->assertOk();
        $response->assertSeeInOrder([
            'Logo variants',
            'Colour tokens',
            'Typography',
            'Buttons',
            'Badges',
            'Cards',
            'Stat tiles',
            'Form fields',
            'Modal',
            'Empty state',
            'Rich text editor',
            'Rendered content (prose)',
            'Shared foundations',
            'Icons',
        ]);
    }

    public function test_styleguide_renders_both_rich_editor_profiles(): void
    {
        $response = $this->get('/styleguide');

        $response->assertOk();
        // Both editor profiles present and wired for the upload endpoint.
        $response->assertSee('data-profile="full"', false);
        $response->assertSee('data-profile="basic"', false);
        $response->assertSee('data-rich-editor', false);
        $response->assertSee(route('editor.upload'), false);
    }
}

