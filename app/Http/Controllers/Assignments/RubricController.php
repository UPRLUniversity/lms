<?php

namespace App\Http\Controllers\Assignments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assignments\RubricRequest;
use App\Models\Rubric;
use App\Services\Assignments\RubricService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The rubric library + grid builder. The grid (criteria rows × level columns) is posted
 * wholesale on save; RubricService rebuilds the criteria set in order. Admins see their
 * own library; auditors browse read-only.
 */
class RubricController extends Controller
{
    public function __construct(private readonly RubricService $rubrics) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Rubric::class);

        $user = $request->user();
        $isAuditor = ! Gate::allows('create', Rubric::class);

        $rubrics = Rubric::query()
            ->when(! $isAuditor, fn ($q) => $q->where('created_by', $user->id))
            ->with('criteria')
            ->withCount('assignments')
            ->orderBy('name')
            ->paginate(20);

        return view('assignments.rubrics.index', [
            'rubrics' => $rubrics,
            'canCreate' => Gate::allows('create', Rubric::class),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Rubric::class);

        return view('assignments.rubrics.edit', [
            'rubric' => new Rubric,
            'canManage' => true,
        ]);
    }

    public function store(RubricRequest $request): RedirectResponse
    {
        $rubric = $this->rubrics->save(new Rubric(['created_by' => $request->user()->id]), $request->validated());

        return redirect()
            ->route('rubrics.edit', $rubric)
            ->with('status', 'Rubric created.');
    }

    public function edit(Rubric $rubric): View
    {
        $this->authorize('view', $rubric);

        $rubric->load('criteria')->loadCount('assignments');

        return view('assignments.rubrics.edit', [
            'rubric' => $rubric,
            'canManage' => Gate::allows('manage', $rubric),
        ]);
    }

    public function update(RubricRequest $request, Rubric $rubric): RedirectResponse
    {
        $this->rubrics->save($rubric, $request->validated());

        return redirect()
            ->route('rubrics.edit', $rubric)
            ->with('status', 'Rubric saved.');
    }

    public function destroy(Rubric $rubric): RedirectResponse
    {
        $this->authorize('manage', $rubric);

        // Assignments referencing it keep working: rubric_id nulls on delete, and
        // existing grades hold their own snapshot.
        $rubric->delete();

        return redirect()
            ->route('rubrics.index')
            ->with('status', 'Rubric deleted. Existing grades keep their scored breakdown.');
    }
}
