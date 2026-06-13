<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    public function test_missing_pages_render_the_branded_404(): void
    {
        $response = $this->get('/this-route-does-not-exist-'.uniqid());

        $response->assertNotFound();
        $response->assertSee('404');
        $response->assertSee('Page not found');
        $response->assertSee('Back to safety');
    }

    public function test_forbidden_responses_render_the_branded_403(): void
    {
        Route::get('/_test/forbidden', fn () => abort(403));

        $response = $this->get('/_test/forbidden');

        $response->assertForbidden();
        $response->assertSee('403');
        $response->assertSee('Access denied');
    }
}
