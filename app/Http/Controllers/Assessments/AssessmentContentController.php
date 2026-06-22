<?php

namespace App\Http\Controllers\Assessments;

use App\Enums\QuestionDifficulty;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentPoolRule;
use App\Models\Course;
use App\Models\QuestionCategory;
use App\Services\Assessments\AssessmentBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The builder's content endpoints (AJAX): the fixed question list (pick + order + points
 * overrides) and the pool rules (CRUD + live availability).
 */
class AssessmentContentController extends Controller
{
    public function __construct(private readonly AssessmentBuilderService $builder) {}

    /**
     * Replace the fixed question list with an ordered selection.
     */
    public function syncQuestions(Request $request, Course $course, Assessment $assessment): JsonResponse
    {
        $this->guard($course, $assessment);

        $data = $request->validate([
            'questions' => ['present', 'array'],
            'questions.*.id' => ['required', 'integer'],
            'questions.*.points_override' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        $this->builder->syncFixedQuestions($assessment, $data['questions']);

        $assessment->load('questions');

        return response()->json([
            'ok' => true,
            'message' => 'Questions updated.',
            'total_points' => $assessment->totalPoints(),
            'count' => $assessment->questions->count(),
            'publish_errors' => $this->builder->validateForPublish($assessment),
        ]);
    }

    public function storeRule(Request $request, Course $course, Assessment $assessment): JsonResponse
    {
        $this->guard($course, $assessment);

        $data = $this->validateRule($request, $course);
        $rule = $this->builder->addPoolRule($assessment, $data);

        return response()->json([
            'ok' => true,
            'message' => 'Pool rule added.',
            'rule_id' => $rule->id,
            'available' => $this->builder->poolAvailability($rule->category_id, $rule->difficulty),
            'publish_errors' => $this->builder->validateForPublish($assessment->refresh()),
        ]);
    }

    public function updateRule(Request $request, Course $course, Assessment $assessment, AssessmentPoolRule $rule): JsonResponse
    {
        $this->guard($course, $assessment);
        abort_unless($rule->assessment_id === $assessment->id, 404);

        $data = $this->validateRule($request, $course);
        $this->builder->updatePoolRule($rule, $data);

        return response()->json([
            'ok' => true,
            'message' => 'Pool rule saved.',
            'available' => $this->builder->poolAvailability($rule->category_id, $rule->difficulty),
            'publish_errors' => $this->builder->validateForPublish($assessment->refresh()),
        ]);
    }

    public function destroyRule(Course $course, Assessment $assessment, AssessmentPoolRule $rule): JsonResponse
    {
        $this->guard($course, $assessment);
        abort_unless($rule->assessment_id === $assessment->id, 404);

        $this->builder->deletePoolRule($rule);

        return response()->json([
            'ok' => true,
            'message' => 'Pool rule removed.',
            'publish_errors' => $this->builder->validateForPublish($assessment->refresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRule(Request $request, Course $course): array
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:question_categories,id'],
            'difficulty' => ['nullable', 'string', 'in:'.implode(',', QuestionDifficulty::values())],
            'count' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        // The category must belong to this course's bank (or be global).
        $category = $course->questionCategories()->find($data['category_id'])
            ?? QuestionCategory::whereNull('course_id')->find($data['category_id']);
        abort_unless($category !== null, 422, 'Choose a category from this course.');

        $data['difficulty'] = isset($data['difficulty'])
            ? QuestionDifficulty::from($data['difficulty'])
            : null;

        return $data;
    }

    private function guard(Course $course, Assessment $assessment): void
    {
        abort_unless($assessment->course_id === $course->id, 404);
        $this->authorize('manage', $assessment);
    }
}
