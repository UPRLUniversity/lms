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
                'bands' => [],
            ],
            'computed_at' => now(),
        ];
    }
}
