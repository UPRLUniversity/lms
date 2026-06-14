<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDepartmentRequest;
use App\Http\Requests\Admin\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\Faculty;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function create(): View
    {
        $this->authorize('create', Department::class);

        return view('admin.departments.create', [
            'faculties' => Faculty::orderBy('name')->get(),
            'selectedFaculty' => (int) request()->query('faculty'),
        ]);
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Department::create([
            'faculty_id' => $data['faculty_id'],
            'name' => $data['name'],
            'slug' => Slug::unique(Department::class, $data['name']),
            'description' => $data['description'] ?? null,
        ]);

        return redirect()
            ->route('admin.faculties.index')
            ->with('status', "Department “{$data['name']}” was created.");
    }

    public function edit(Department $department): View
    {
        $this->authorize('update', $department);

        return view('admin.departments.edit', [
            'department' => $department,
            'faculties' => Faculty::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $data = $request->validated();

        $department->update([
            'faculty_id' => $data['faculty_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return redirect()
            ->route('admin.faculties.index')
            ->with('status', 'Department updated.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $this->authorize('delete', $department);

        if ($department->courses()->exists()) {
            return back()->with('status', 'That department still has courses and cannot be deleted.');
        }

        $department->delete();

        return redirect()
            ->route('admin.faculties.index')
            ->with('status', 'Department deleted.');
    }
}
