<?php

namespace App\Http\Controllers;

use App\Services\Site\PublicSiteService;
use Illuminate\View\View;

/**
 * The public homepage.
 *
 * Replaces the route closure that used to return a static welcome view: the page now
 * leads with real programmes, real figures and real courses, and every call to action
 * lands somewhere a signed-out visitor can actually go (the catalogue, a programme
 * page, the cart, /verify).
 */
class HomeController extends Controller
{
    public function __construct(private readonly PublicSiteService $site) {}

    public function __invoke(): View
    {
        return view('welcome', [
            'stats' => $this->site->stats(),
            'programmes' => $this->site->programmes(),
            'featured' => $this->site->featuredCourses(6),
        ]);
    }
}
