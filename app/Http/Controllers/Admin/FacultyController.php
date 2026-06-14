<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFacultyRequest;
use App\Http\Requests\Admin\UpdateFacultyRequest;
use App\Models\Faculty;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FacultyController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Faculty::class);

        return view('admin.faculties.index', [
            'faculties' => Faculty::query()
                ->withCount(['departments', 'courses'])
                ->with('departments')
                ->orderBy('name')
                ->get(),
            'canManage' => request()->user()->can('create', Faculty::class),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Faculty::class);

        return view('admin.faculties.create');
    }

    public function store(StoreFacultyRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Faculty::create([
            'name' => $data['name'],
            'slug' => Slug::unique(Faculty::class, $data['name']),
            'description' => $data['description'] ?? null,
        ]);

        return redirect()
            ->route('admin.faculties.index')
            ->with('status', "Faculty “{$data['name']}” was created.");
    }

    public function edit(Faculty $faculty): View
    {
        $this->authorize('update', $faculty);

        return view('admin.faculties.edit', ['faculty' => $faculty]);
    }

    public function update(UpdateFacultyRequest $request, Faculty $faculty): RedirectResponse
    {
        $data = $request->validated();

        $faculty->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return redirect()
            ->route('admin.faculties.index')
            ->with('status', 'Faculty updated.');
    }

    public function destroy(Faculty $faculty): RedirectResponse
    {
        $this->authorize('delete', $faculty);

        if ($faculty->courses()->exists()) {
            return back()->with('status', 'That faculty still has courses and cannot be deleted.');
        }

        $faculty->delete();

        return redirect()
            ->route('admin.faculties.index')
            ->with('status', 'Faculty deleted.');
    }
}
