<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseGradeRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseGradeRecord>
 */
class CourseGradeRecordFactory extends Factory
{
    protected $model = CourseGradeRecord::class;

    public function definition(): array
    {
        $percent = $this->faker->numberBetween(40, 100);

        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'version' => 1,
            'superseded_at' => null,
            'final_percent' => $percent,
            'grade_label' => $percent >= 70 ? 'A' : ($percent >= 60 ? 'B' : ($percent >= 50 ? 'C' : 'F')),
            'grade_point' => $percent >= 70 ? 5.00 : ($percent >= 60 ? 4.00 : ($percent >= 50 ? 3.00 : 0.00)),
            'scale_snapshot' => [
                'grade_scale_id' => 1,
                'name' => 'NUC Standard (5.0)',
                'scale_limit' => 5.00,
                'display_mode' => 'both',
                'show_scale_limit' => true,
                'separator' => '/',
                'bands' => [
                    ['label' => 'A', 'grade_point' => 5.00, 'is_pass' => true, 'min_percent' => 70, 'max_percent' => 100, 'color' => 'success'],
                    ['label' => 'B', 'grade_point' => 4.00, 'is_pass' => true, 'min_percent' => 60, 'max_percent' => 69, 'color' => 'gold'],
                    ['label' => 'C', 'grade_point' => 3.00, 'is_pass' => true, 'min_percent' => 50, 'max_percent' => 59, 'color' => 'ink'],
                    ['label' => 'F', 'grade_point' => 0.00, 'is_pass' => false, 'min_percent' => 0, 'max_percent' => 49, 'color' => 'crimson'],
                ],
            ],
            'computed_at' => now(),
        ];
    }

    /**
     * A recorded fail — the outcome most tests need to construct deliberately, since the
     * default percentage range mostly lands on a pass.
     */
    public function failed(): static
    {
        return $this->state(fn () => [
            'final_percent' => 32,
            'grade_label' => 'F',
            'grade_point' => 0.00,
        ]);
    }

    /**
     * A record stamped before `is_pass` existed: the snapshot names bands but none of them
     * says whether it passes, so the verdict has to fall back to the grade point.
     */
    public function legacySnapshot(): static
    {
        return $this->state(function (array $attributes) {
            $snapshot = $attributes['scale_snapshot'];
            $snapshot['bands'] = collect($snapshot['bands'])
                ->map(function (array $band) {
                    unset($band['is_pass']);

                    return $band;
                })
                ->all();

            return ['scale_snapshot' => $snapshot];
        });
    }
}
