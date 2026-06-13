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
            'Icons',
        ]);
    }
}
