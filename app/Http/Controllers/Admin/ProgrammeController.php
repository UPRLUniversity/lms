<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MediaPurpose;
use App\Enums\ProgressionRule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProgrammeRequest;
use App\Http\Requests\Admin\UpdateProgrammeRequest;
use App\Models\Programme;
use App\Services\Courses\ProgressionAuditService;
use App\Services\Media\MediaUploadService;
use App\Support\Slug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Programmes and their parts — the qualification structure courses are packaged
 * under. Mirrors the faculties/departments screens: parts are managed inline on the
 * index rather than getting their own listing, because a part is meaningless outside
 * its programme.
 */
class ProgrammeController extends Controller
{
    /** How many affected students the inline impact panel lists before it summarises. */
    private const IMPACT_ROW_LIMIT = 100;

    public function index(): View
    {
        $this->authorize('viewAny', Programme::class);

        return view('admin.programmes.index', [
            'programmes' => Programme::query()
                // Eager-load the whole tree in three queries: the part list, each part's
                // courses (for the credit sums), and the pivot data those sums read.
                ->with(['parts.courses' => fn ($q) => $q->select('courses.id', 'courses.title', 'courses.code')])
                ->withCount('parts')
                ->ordered()
                ->get(),
            'canManage' => request()->user()->can('create', Programme::class),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Programme::class);

        return view('admin.programmes.create');
    }

    public function store(StoreProgrammeRequest $request, MediaUploadService $media): RedirectResponse
    {
        $data = $request->validated();

        $programme = Programme::create([
            'name' => $data['name'],
            'code' => $data['code'],
            // Slug from the CODE, not the name: /courses?programme=cpr is what the
            // prospectus, the staff and the reference site all call it, and
            // "professional-certificate-in-public-relations" is unusable in a filter URL.
            // The code is already unique and validated alpha_num, so it is URL-safe.
            'slug' => Slug::unique(Programme::class, $data['code']),
            'tagline' => $data['tagline'] ?? null,
            'description' => $data['description'] ?? null,
            'registration_fee' => $data['registration_fee'] ?? 0,
            'administration_fee' => $data['administration_fee'] ?? 0,
            'per_paper_fee' => $data['per_paper_fee'] ?? 0,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'progression_rule' => $data['progression_rule'] ?? ProgressionRule::Open->value,
            'position' => (int) Programme::max('position') + 1,
        ]);

        if ($request->hasFile('cover')) {
            $media->upload($request->file('cover'), MediaPurpose::ProgrammeCovers, $programme);
        }

        return redirect()
            ->route('admin.programmes.index')
            ->with('status', "Programme “{$programme->name}” was created.");
    }

    public function edit(Programme $programme): View
    {
        $this->authorize('update', $programme);

        return view('admin.programmes.edit', ['programme' => $programme]);
    }

    /**
     * "Who would sequential progression block?", answered on the form itself.
     *
     * Fetched when the admin selects the sequential option, not on page load: the audit
     * walks every live enrolment in the programme, and an admin editing a fee has no reason
     * to pay for it. The row list is capped while the counts stay honest, because a
     * programme with four hundred affected students needs the number to make the decision,
     * not four hundred rows inside a form.
     */
    public function progressionImpact(Programme $programme, ProgressionAuditService $audit): JsonResponse
    {
        $this->authorize('update', $programme);

        $impact = $audit->forProgramme($programme);

        return response()->json([
            'checked' => $impact->checked,
            'blocked' => $impact->blockedCount(),
            'students' => $impact->studentCount(),
            'rows' => $impact->rows->take(self::IMPACT_ROW_LIMIT)->values(),
            'truncated' => max(0, $impact->blockedCount() - self::IMPACT_ROW_LIMIT),
        ]);
    }

    public function update(UpdateProgrammeRequest $request, Programme $programme, MediaUploadService $media): RedirectResponse
    {
        $data = $request->validated();

        $programme->update([
            'name' => $data['name'],
            'code' => $data['code'],
            'tagline' => $data['tagline'] ?? null,
            'description' => $data['description'] ?? null,
            'registration_fee' => $data['registration_fee'] ?? 0,
            'administration_fee' => $data['administration_fee'] ?? 0,
            'per_paper_fee' => $data['per_paper_fee'] ?? 0,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'progression_rule' => $data['progression_rule'] ?? ProgressionRule::Open->value,
        ]);

        // Replace the cover (keep exactly one), same pattern as a course cover.
        if ($request->hasFile('cover')) {
            $existing = $programme->cover();
            $media->upload($request->file('cover'), MediaPurpose::ProgrammeCovers, $programme);
            if ($existing) {
                $media->destroy($existing);
            }
        }

        return redirect()
            ->route('admin.programmes.index')
            ->with('status', 'Programme updated.');
    }

    public function destroy(Programme $programme): RedirectResponse
    {
        $this->authorize('delete', $programme);

        // Deleting cascades to parts and to placement rows, which would silently strip
        // every affected course out of the qualification it is examined under. Refuse
        // while anything still points here — the same guard faculties use.
        if ($programme->placements()->exists()) {
            return back()->with('error', 'That programme still has courses placed in it. Remove them first.');
        }

        $name = $programme->name;
        $programme->delete();

        return redirect()
            ->route('admin.programmes.index')
            ->with('status', "Programme “{$name}” deleted.");
    }
}
