<?php

namespace App\Http\Controllers\Assessments;

use App\Enums\QuestionDifficulty;
use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessments\QuestionRequest;
use App\Models\Course;
use App\Models\Question;
use App\Services\Assessments\QuestionBankService;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The course-scoped question bank: a filterable table (type, category, difficulty, search)
 * plus per-type authoring over AJAX, duplicate, safe-delete and import from another of the
 * instructor's courses.
 */
class QuestionController extends Controller
{
    public function __construct(private readonly QuestionBankService $bank) {}

    /**
     * Filterable, paginated bank table. Returns just the table partial for AJAX so the
     * live filters never reload the page.
     */
    public function index(Request $request, Course $course): ViewContract
    {
        $this->authorize('view', $course);

        $search = trim((string) $request->query('search', ''));
        $type = (string) $request->query('type', '');
        $difficulty = (string) $request->query('difficulty', '');
        $category = (string) $request->query('category', '');

        $questions = $course->questions()
            ->with('category')
            ->withCount('assessments')
            ->when($search !== '', fn ($q) => $q->where('prompt', 'like', "%{$search}%"))
            ->when(in_array($type, QuestionType::values(), true), fn ($q) => $q->where('type', $type))
            ->when(in_array($difficulty, QuestionDifficulty::values(), true), fn ($q) => $q->where('difficulty', $difficulty))
            ->when(is_numeric($category), fn ($q) => $q->where('category_id', (int) $category))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $data = [
            'course' => $course,
            'questions' => $questions,
            'categories' => $course->questionCategories()->orderBy('name')->get(),
            'types' => QuestionType::cases(),
            'difficulties' => QuestionDifficulty::cases(),
            'filters' => compact('search', 'type', 'difficulty', 'category'),
        ];

        if ($request->ajax() || $request->wantsJson()) {
            return view('assessments.questions._table', $data);
        }

        return view('assessments.questions.index', $data);
    }

    /**
     * The full-page question editor for a new question (type chosen via ?type=).
     */
    public function create(Request $request, Course $course): ViewContract
    {
        $this->authorize('manageCurriculum', $course);

        $type = QuestionType::tryFrom((string) $request->query('type', QuestionType::McqSingle->value))
            ?? QuestionType::McqSingle;

        return view('assessments.questions.edit', [
            'course' => $course,
            'question' => null,
            'type' => $type,
            'categories' => $course->questionCategories()->orderBy('name')->get(),
        ]);
    }

    /**
     * The full-page editor for an existing question.
     */
    public function edit(Course $course, Question $question): ViewContract
    {
        $this->assertBelongs($course, $question);
        $this->authorize('update', $question);

        return view('assessments.questions.edit', [
            'course' => $course,
            'question' => $question,
            'type' => $question->type,
            'categories' => $course->questionCategories()->orderBy('name')->get(),
        ]);
    }

    public function store(QuestionRequest $request, Course $course): RedirectResponse
    {
        $this->bank->create(
            array_merge($request->validated(), ['course_id' => $course->id]),
            $request->user(),
        );

        return redirect()
            ->route('questions.index', $course)
            ->with('status', 'Question added to the bank.');
    }

    public function update(QuestionRequest $request, Course $course, Question $question): RedirectResponse
    {
        $this->assertBelongs($course, $question);
        $this->authorize('update', $question);

        $this->bank->update($question, $request->validated());

        return redirect()
            ->route('questions.index', $course)
            ->with('status', 'Question saved.');
    }

    public function duplicate(Course $course, Question $question): JsonResponse
    {
        $this->assertBelongs($course, $question);
        $this->authorize('duplicate', $question);

        $copy = $this->bank->duplicate($question, request()->user());

        return response()->json(['ok' => true, 'message' => 'Question duplicated.', 'question_id' => $copy->id]);
    }

    public function destroy(Course $course, Question $question): JsonResponse
    {
        $this->assertBelongs($course, $question);
        $this->authorize('delete', $question);

        try {
            $this->bank->delete($question);
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => 'Question removed.']);
    }

    /**
     * Browse the instructor's other courses' questions to import from.
     */
    public function importForm(Request $request, Course $course): ViewContract
    {
        $this->authorize('update', $course);

        $sourceCourses = Course::forInstructor($request->user())
            ->where('id', '!=', $course->id)
            ->withCount('questions')
            ->having('questions_count', '>', 0)
            ->orderBy('title')
            ->get();

        $sourceId = (int) $request->query('source', $sourceCourses->first()?->id);
        $source = $sourceCourses->firstWhere('id', $sourceId);

        $questions = $source
            ? $source->questions()->with('category')->latest()->get()
            : collect();

        return view('assessments.questions.import', [
            'course' => $course,
            'sourceCourses' => $sourceCourses,
            'source' => $source,
            'questions' => $questions,
        ]);
    }

    public function import(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('update', $course);

        $data = $request->validate([
            'source_course_id' => ['required', 'integer', 'exists:courses,id'],
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['integer'],
        ]);

        $source = Course::findOrFail($data['source_course_id']);
        $this->authorize('view', $source);

        $count = $this->bank->importFromCourse($source, $course, $data['question_ids'], $request->user());

        return redirect()
            ->route('questions.index', $course)
            ->with('status', "{$count} question(s) imported.");
    }

    private function assertBelongs(Course $course, Question $question): void
    {
        abort_unless($question->course_id === $course->id, 404);
    }
}
