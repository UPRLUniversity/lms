<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    // Section 13 turned "/" from a static view into a real page (programmes, live
    // counts, featured courses), so the smoke test needs a schema to query. The
    // homepage's actual behaviour is covered in Tests\Feature\Public\HomepageTest.
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
