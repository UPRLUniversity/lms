<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Programme;
use App\Services\Courses\ProgressionService;
use App\Services\Site\PublicSiteService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public programme landing pages — the qualification ladder a visitor picks from
 * before they ever look at an individual course.
 *
 * No auth: this is prospectus material. An inactive programme 404s rather than
 * rendering, so switching one off in admin takes it off the public site immediately.
 */
class ProgrammeController extends Controller
{
    public function __construct(
        private readonly PublicSiteService $site,
        private readonly ProgressionService $progression,
    ) {}

    public function index(): View
    {
        return view('public.programmes.index', [
            'programmes' => $this->site->programmes(),
        ]);
    }

    public function show(Request $request, Programme $programme): View
    {
        abort_unless($programme->is_active, 404);

        $curriculum = $this->site->programmeCurriculum($programme);

        return view('public.programmes.show', [
            'programme' => $curriculum,
            // Empty for a guest and for an `open` programme — there is no ladder to draw
            // when nothing unlocks anything, and drawing one would invent a rule.
            'partStates' => $this->progression->partStatesFor($request->user(), $curriculum),
        ]);
    }
}
