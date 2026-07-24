<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GradeScaleRequest;
use App\Models\GradeScale;
use App\Services\Grades\GradeScaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Scale administration: list, create, edit. The band grid is posted wholesale on save
 * (mirrors the rubric builder); GradeScaleService rebuilds the bands set in order and
 * enforces the coverage/monotonicity/scale-limit invariants before anything is written.
 */
class GradeScaleController extends Controller
{
    public function __construct(private readonly GradeScaleService $scales) {}

    public function index(): View
    {
        $this->authorize('viewAny', GradeScale::class);

        return view('admin.grade-scales.index', [
            'scales' => GradeScale::query()
                ->withCount('courses')
                ->with('bands')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', GradeScale::class);

        return view('admin.grade-scales.edit', [
            'scale' => new GradeScale,
        ]);
    }

    public function store(GradeScaleRequest $request): RedirectResponse
    {
        $scale = $this->scales->save(new GradeScale, $request->validated());

        return redirect()
            ->route('admin.grade-scales.edit', $scale)
            ->with('status', "Grade scale “{$scale->name}” was created.");
    }

    public function edit(GradeScale $gradeScale): View
    {
        $this->authorize('update', $gradeScale);

        $gradeScale->load('bands');

        return view('admin.grade-scales.edit', [
            'scale' => $gradeScale,
        ]);
    }

    public function update(GradeScaleRequest $request, GradeScale $gradeScale): RedirectResponse
    {
        $this->scales->save($gradeScale, $request->validated());

        return redirect()
            ->route('admin.grade-scales.edit', $gradeScale)
            ->with('status', 'Grade scale saved.');
    }

    public function archive(GradeScale $gradeScale): RedirectResponse
    {
        $this->authorize('update', $gradeScale);

        try {
            $this->scales->archive($gradeScale);
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.grade-scales.index')
                ->with('error', $e->validator->errors()->first());
        }

        return redirect()
            ->route('admin.grade-scales.index')
            ->with('status', "“{$gradeScale->name}” was archived. Courses already using it are unaffected.");
    }

    public function restore(GradeScale $gradeScale): RedirectResponse
    {
        $this->authorize('update', $gradeScale);

        $this->scales->restore($gradeScale);

        return redirect()
            ->route('admin.grade-scales.index')
            ->with('status', "“{$gradeScale->name}” is active again.");
    }
}
