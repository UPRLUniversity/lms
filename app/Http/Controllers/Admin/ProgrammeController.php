<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MediaPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProgrammeRequest;
use App\Http\Requests\Admin\UpdateProgrammeRequest;
use App\Models\Programme;
use App\Services\Media\MediaUploadService;
use App\Support\Slug;
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
