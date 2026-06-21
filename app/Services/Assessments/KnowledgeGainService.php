<?php

namespace App\Services\Assessments;

use App\Enums\AssessmentPlacement;
use App\Enums\AssessmentStatus;
use App\Enums\AttemptStatus;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\Module;
use App\Models\User;

/**
 * The pre/post "knowledge-gain" insight: when a module has both a pre- and a post-module
 * assessment and a student has a graded attempt at each, the lift from one to the other.
 * Powers the student's result card + Learning History and the instructor's class average.
 */
class KnowledgeGainService
{
    /**
     * Gain for the student of a freshly-graded post-module attempt, or null when the pieces
     * aren't all in place (not a post attempt, not graded, or no graded pre attempt).
     *
     * @return array{pre: int, post: int, gain: int, module_title: string}|null
     */
    public function forStudentAttempt(Attempt $attempt): ?array
    {
        $attempt->loadMissing('assessment.module');
        $assessment = $attempt->assessment;

        if (! $assessment
            || $assessment->placement !== AssessmentPlacement::PostModule
            || $attempt->status !== AttemptStatus::Graded
            || ! $assessment->module) {
            return null;
        }

        return $this->forStudentModule($attempt->user ?? $attempt->user()->first(), $assessment->module);
    }

    /**
     * Gain for a given student on a module, or null when both graded attempts don't exist.
     *
     * @return array{pre: int, post: int, gain: int, module_title: string}|null
     */
    public function forStudentModule(User $user, Module $module): ?array
    {
        $pre = $this->placementAssessment($module, AssessmentPlacement::PreModule);
        $post = $this->placementAssessment($module, AssessmentPlacement::PostModule);

        if (! $pre || ! $post) {
            return null;
        }

        $preAttempt = $pre->bestAttemptFor($user);
        $postAttempt = $post->bestAttemptFor($user);

        if (! $preAttempt || ! $postAttempt) {
            return null;
        }

        $prePct = (int) $preAttempt->percentage;
        $postPct = (int) $postAttempt->percentage;

        return [
            'pre' => $prePct,
            'post' => $postPct,
            'gain' => $postPct - $prePct,
            'module_title' => $module->title,
        ];
    }

    /**
     * Class-level average gain on a module: averaged over students who have a graded attempt
     * at BOTH the pre and post assessments. Null when the pre/post pair isn't set up.
     *
     * @return array{pre: int, post: int, gain: int, students: int, module_title: string}|null
     */
    public function classAverageForModule(Module $module): ?array
    {
        $pre = $this->placementAssessment($module, AssessmentPlacement::PreModule);
        $post = $this->placementAssessment($module, AssessmentPlacement::PostModule);

        if (! $pre || ! $post) {
            return null;
        }

        $preBest = $this->bestPercentByUser($pre);
        $postBest = $this->bestPercentByUser($post);

        $bothUsers = array_intersect(array_keys($preBest), array_keys($postBest));

        if (empty($bothUsers)) {
            return null;
        }

        $preAvg = $this->average(array_map(fn ($id) => $preBest[$id], $bothUsers));
        $postAvg = $this->average(array_map(fn ($id) => $postBest[$id], $bothUsers));

        return [
            'pre' => $preAvg,
            'post' => $postAvg,
            'gain' => $postAvg - $preAvg,
            'students' => count($bothUsers),
            'module_title' => $module->title,
        ];
    }

    private function placementAssessment(Module $module, AssessmentPlacement $placement): ?Assessment
    {
        return $module->assessments()
            ->where('placement', $placement->value)
            ->where('status', AssessmentStatus::Published->value)
            ->orderBy('position')
            ->first();
    }

    /**
     * user_id => best graded percentage on an assessment.
     *
     * @return array<int, int>
     */
    private function bestPercentByUser(Assessment $assessment): array
    {
        return $assessment->attempts()
            ->where('status', AttemptStatus::Graded->value)
            ->get(['user_id', 'percentage'])
            ->groupBy('user_id')
            ->map(fn ($rows) => (int) $rows->max('percentage'))
            ->all();
    }

    /**
     * @param  array<int, int>  $values
     */
    private function average(array $values): int
    {
        return empty($values) ? 0 : (int) round(array_sum($values) / count($values));
    }
}
