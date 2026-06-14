<?php

namespace Database\Seeders;

use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\CourseVisibility;
use App\Enums\LessonType;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\User;
use App\Support\Slug;
use Illuminate\Database\Seeder;

/**
 * UPRL-realistic academic structure and a clickable catalogue: two faculties, their
 * departments, and a spread of courses (mostly published, plus one in review and one
 * draft so the workflow is demonstrable). Idempotent — keyed on slugs/codes.
 */
class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $instructors = User::role(Role::Instructor->value)->orderBy('id')->get();
        if ($instructors->isEmpty()) {
            $instructors = collect([User::factory()->create()->assignRole(Role::Instructor->value)]);
        }

        $structure = $this->structure();

        $faculties = [];
        $departments = [];

        foreach ($structure as $facultyName => $deptNames) {
            $faculty = Faculty::updateOrCreate(
                ['slug' => Slug::unique(Faculty::class, $facultyName)],
                ['name' => $facultyName, 'description' => 'Part of the '.config('brand.university').'.'],
            );
            $faculties[$facultyName] = $faculty;

            foreach ($deptNames as $deptName) {
                $departments[$deptName] = Department::updateOrCreate(
                    ['name' => $deptName, 'faculty_id' => $faculty->id],
                    ['slug' => Slug::unique(Department::class, $deptName)],
                );
            }
        }

        foreach ($this->courses() as $i => $data) {
            $department = $departments[$data['department']];
            $lead = $instructors[$i % $instructors->count()];

            $course = Course::updateOrCreate(
                ['code' => $data['code']],
                [
                    'title' => $data['title'],
                    'slug' => Slug::unique(Course::class, $data['title']),
                    'department_id' => $department->id,
                    'level' => $data['level'],
                    'summary' => $data['summary'],
                    'description' => '<p>'.$data['description'].'</p>',
                    'learning_objectives' => $data['objectives'],
                    'duration_minutes' => 0,
                    'status' => $data['status'],
                    'visibility' => CourseVisibility::PublicCatalogue->value,
                    'created_by' => $lead->id,
                    'published_at' => $data['status'] === CourseStatus::Published->value ? now() : null,
                    'review_note' => $data['review_note'] ?? null,
                ],
            );

            $course->instructors()->syncWithoutDetaching([$lead->id => ['is_lead' => true]]);

            // A co-instructor on the first couple of courses.
            if ($i < 2 && $instructors->count() > 1) {
                $course->instructors()->syncWithoutDetaching([$instructors[($i + 1) % $instructors->count()]->id => ['is_lead' => false]]);
            }

            $this->buildCurriculum($course, $data['modules']);
            $course->update(['duration_minutes' => $course->lessons()->sum('duration_minutes')]);
        }
    }

    /**
     * Recreate a course's modules and lessons from the blueprint (idempotent).
     *
     * @param  array<int, array{title: string, lessons: array<int, array<string, mixed>>}>  $modules
     */
    private function buildCurriculum(Course $course, array $modules): void
    {
        $course->modules()->delete();

        foreach ($modules as $mIndex => $module) {
            $created = $course->modules()->create([
                'title' => $module['title'],
                'position' => $mIndex + 1,
            ]);

            foreach ($module['lessons'] as $lIndex => $lesson) {
                $type = $lesson['type'];

                $created->lessons()->create([
                    'title' => $lesson['title'],
                    'type' => $type->value,
                    'position' => $lIndex + 1,
                    'duration_minutes' => $lesson['minutes'] ?? fake()->numberBetween(5, 25),
                    'is_free_preview' => $lesson['preview'] ?? false,
                    'content_text' => $type === LessonType::Text ? '<p>'.fake()->paragraphs(2, true).'</p>' : null,
                    'video_url' => $type === LessonType::Video ? ($lesson['url'] ?? null) : null,
                    'video_provider' => $type === LessonType::Video ? (str_contains($lesson['url'] ?? '', 'vimeo') ? 'vimeo' : 'youtube') : null,
                    'external_url' => $type === LessonType::ExternalLink ? ($lesson['url'] ?? 'https://uprl.edu.ng') : null,
                ]);
            }
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function structure(): array
    {
        return [
            'Faculty of Communication & Media Studies' => [
                'Department of Public Relations',
                'Department of Journalism & Media',
                'Department of Strategic Communication',
            ],
            'College of Leadership & Development Studies' => [
                'Department of Organisational Leadership',
                'Department of Development Studies',
                'Department of Public Administration',
            ],
        ];
    }

    /**
     * Course blueprints. A YouTube/Vimeo placeholder video is used for previews.
     *
     * @return array<int, array<string, mixed>>
     */
    private function courses(): array
    {
        $yt = fn (string $id) => "https://www.youtube.com/watch?v={$id}";
        $V = LessonType::Video;
        $T = LessonType::Text;
        $L = LessonType::ExternalLink;

        return [
            [
                'code' => 'PRL101',
                'title' => 'PRL101: Foundations of Public Relations',
                'department' => 'Department of Public Relations',
                'level' => CourseLevel::Undergraduate->value,
                'status' => CourseStatus::Published->value,
                'summary' => 'Master the principles, history and practice of modern public relations in a Nigerian context.',
                'description' => 'An introduction to the discipline of public relations: its theories, publics, tools and ethics. You will build the vocabulary and core skills every PR practitioner needs.',
                'objectives' => ['Define public relations and its role in organisations', 'Identify and segment publics', 'Draft a basic press release', 'Apply the RACE planning model'],
                'modules' => [
                    ['title' => 'What is Public Relations?', 'lessons' => [
                        ['type' => $V, 'title' => 'Welcome & course overview', 'url' => $yt('YE7VzlLtp-4'), 'preview' => true, 'minutes' => 6],
                        ['type' => $T, 'title' => 'Defining PR and its publics', 'minutes' => 15],
                        ['type' => $L, 'title' => 'Further reading: PRSA fundamentals', 'url' => 'https://www.prsa.org/about/all-about-pr', 'minutes' => 10],
                    ]],
                    ['title' => 'The PR Process', 'lessons' => [
                        ['type' => $V, 'title' => 'The RACE model explained', 'url' => $yt('aqz-KE-bpKQ'), 'minutes' => 12],
                        ['type' => $T, 'title' => 'Research and situation analysis', 'minutes' => 18],
                    ]],
                    ['title' => 'Writing for PR', 'lessons' => [
                        ['type' => $T, 'title' => 'Anatomy of a press release', 'minutes' => 20],
                        ['type' => $T, 'title' => 'Tone, clarity and the inverted pyramid', 'minutes' => 14],
                    ]],
                ],
            ],
            [
                'code' => 'LDS201',
                'title' => 'LDS201: Organisational Leadership',
                'department' => 'Department of Organisational Leadership',
                'level' => CourseLevel::Postgraduate->value,
                'status' => CourseStatus::Published->value,
                'summary' => 'Develop the leadership judgement to guide teams and organisations through change.',
                'description' => 'A postgraduate exploration of leadership theory and practice — from motivation and influence to leading organisational change with character.',
                'objectives' => ['Compare major leadership theories', 'Diagnose team motivation', 'Lead a change initiative', 'Reflect on your leadership values'],
                'modules' => [
                    ['title' => 'Theories of Leadership', 'lessons' => [
                        ['type' => $V, 'title' => 'From traits to transformation', 'url' => $yt('lmyZMtPVodo'), 'preview' => true, 'minutes' => 9],
                        ['type' => $T, 'title' => 'Situational leadership', 'minutes' => 16],
                    ]],
                    ['title' => 'Leading People', 'lessons' => [
                        ['type' => $T, 'title' => 'Motivation and influence', 'minutes' => 18],
                        ['type' => $V, 'title' => 'Difficult conversations', 'url' => $yt('YE7VzlLtp-4'), 'minutes' => 11],
                    ]],
                ],
            ],
            [
                'code' => 'PRL305',
                'title' => 'Crisis Communication Essentials',
                'department' => 'Department of Strategic Communication',
                'level' => CourseLevel::Professional->value,
                'status' => CourseStatus::Published->value,
                'summary' => 'Prepare, respond and recover: a practical playbook for communicating under pressure.',
                'description' => 'When the unexpected hits, communication makes or breaks an organisation. Learn to build a crisis plan, manage the newsroom and protect reputation.',
                'objectives' => ['Build a crisis communication plan', 'Run a holding-statement drill', 'Manage stakeholders during a crisis', 'Evaluate post-crisis reputation'],
                'modules' => [
                    ['title' => 'Before the Crisis', 'lessons' => [
                        ['type' => $V, 'title' => 'Why crisis planning matters', 'url' => $yt('aqz-KE-bpKQ'), 'preview' => true, 'minutes' => 7],
                        ['type' => $T, 'title' => 'Risk mapping and scenarios', 'minutes' => 17],
                    ]],
                    ['title' => 'During the Crisis', 'lessons' => [
                        ['type' => $T, 'title' => 'The first hour: holding statements', 'minutes' => 13],
                        ['type' => $L, 'title' => 'Template: crisis statement', 'url' => 'https://uprl.edu.ng/resources/crisis-template', 'minutes' => 5],
                    ]],
                ],
            ],
            [
                'code' => 'JRN210',
                'title' => 'Media Writing & Storytelling',
                'department' => 'Department of Journalism & Media',
                'level' => CourseLevel::Undergraduate->value,
                'status' => CourseStatus::Published->value,
                'summary' => 'Find the story, structure it well, and write copy that holds a reader.',
                'description' => 'A craft course in writing for media — news, features and digital — grounded in clarity, accuracy and narrative.',
                'objectives' => ['Find and pitch a story', 'Structure a news article', 'Write a compelling feature lead', 'Edit for clarity'],
                'modules' => [
                    ['title' => 'Finding the Story', 'lessons' => [
                        ['type' => $V, 'title' => 'News sense', 'url' => $yt('lmyZMtPVodo'), 'preview' => true, 'minutes' => 8],
                        ['type' => $T, 'title' => 'Sources and verification', 'minutes' => 15],
                    ]],
                    ['title' => 'Telling the Story', 'lessons' => [
                        ['type' => $T, 'title' => 'Leads that hook', 'minutes' => 12],
                        ['type' => $T, 'title' => 'Structure: news vs feature', 'minutes' => 16],
                    ]],
                ],
            ],
            [
                'code' => 'PRL220',
                'title' => 'Strategic Campaign Planning',
                'department' => 'Department of Public Relations',
                'level' => CourseLevel::Undergraduate->value,
                'status' => CourseStatus::Published->value,
                'summary' => 'Turn objectives into measurable, creative communication campaigns.',
                'description' => 'From insight to evaluation — plan integrated campaigns with clear goals, audiences, messages, tactics and metrics.',
                'objectives' => ['Set SMART campaign objectives', 'Build an audience and message matrix', 'Choose channels and tactics', 'Define evaluation metrics'],
                'modules' => [
                    ['title' => 'Insight & Objectives', 'lessons' => [
                        ['type' => $V, 'title' => 'From brief to insight', 'url' => $yt('YE7VzlLtp-4'), 'preview' => true, 'minutes' => 9],
                        ['type' => $T, 'title' => 'Writing SMART objectives', 'minutes' => 14],
                    ]],
                    ['title' => 'Execution & Evaluation', 'lessons' => [
                        ['type' => $T, 'title' => 'The channel mix', 'minutes' => 13],
                        ['type' => $T, 'title' => 'Measuring what matters', 'minutes' => 15],
                    ]],
                ],
            ],
            [
                'code' => 'LDS110',
                'title' => 'Ethics & Character in Leadership',
                'department' => 'Department of Development Studies',
                'level' => CourseLevel::Certificate->value,
                'status' => CourseStatus::Published->value,
                'summary' => 'Lead with integrity: an exploration of ethics, values and character.',
                'description' => 'Grounded in UPRL’s motto, this course examines the ethical foundations of leadership and the daily practice of character.',
                'objectives' => ['Reason through ethical dilemmas', 'Articulate a personal code', 'Build trust through consistency', 'Lead by example'],
                'modules' => [
                    ['title' => 'Foundations of Ethics', 'lessons' => [
                        ['type' => $V, 'title' => 'Why character matters', 'url' => $yt('aqz-KE-bpKQ'), 'preview' => true, 'minutes' => 6],
                        ['type' => $T, 'title' => 'Frameworks for ethical reasoning', 'minutes' => 18],
                    ]],
                    ['title' => 'Character in Practice', 'lessons' => [
                        ['type' => $T, 'title' => 'Trust and consistency', 'minutes' => 12],
                    ]],
                ],
            ],
            // One course in review (demonstrates the workflow queue).
            [
                'code' => 'SCM330',
                'title' => 'Digital & Social Media Strategy',
                'department' => 'Department of Strategic Communication',
                'level' => CourseLevel::Professional->value,
                'status' => CourseStatus::Review->value,
                'summary' => 'Plan, publish and measure communication across digital and social channels.',
                'description' => 'A practical strategy course for the social age — content planning, community management and analytics.',
                'objectives' => ['Build a content calendar', 'Grow and manage a community', 'Read social analytics'],
                'modules' => [
                    ['title' => 'Strategy', 'lessons' => [
                        ['type' => $V, 'title' => 'The social landscape', 'url' => $yt('lmyZMtPVodo'), 'minutes' => 8],
                        ['type' => $T, 'title' => 'Content pillars', 'minutes' => 14],
                    ]],
                ],
            ],
            // One draft, returned with a note (demonstrates the in-app review note).
            [
                'code' => 'PAD120',
                'title' => 'Introduction to Public Administration',
                'department' => 'Department of Public Administration',
                'level' => CourseLevel::Undergraduate->value,
                'status' => CourseStatus::Draft->value,
                'review_note' => 'Good start — please add a second module and a course summary before resubmitting.',
                'summary' => 'The structures and principles of public administration.',
                'description' => 'An introductory survey of public administration in Nigeria and beyond.',
                'objectives' => ['Describe the structure of government', 'Explain bureaucracy'],
                'modules' => [
                    ['title' => 'Foundations', 'lessons' => [
                        ['type' => $T, 'title' => 'What is public administration?', 'minutes' => 12],
                    ]],
                ],
            ],
        ];
    }
}
