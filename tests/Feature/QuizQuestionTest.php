<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AssesmentModule\Models\Question;
use Modules\AssesmentModule\Models\Quiz;
use Modules\LearningModule\Models\Course;
use Modules\LearningModule\Models\CourseCategory;
use Modules\UserMangementModule\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class QuizQuestionTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;
    private string $token;
    private Course $course;
    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'api']);

        $permissions = [
            'list-quiz', 'show-quiz', 'create-quiz', 'update-quiz', 'delete-quiz',
            'publish-quiz', 'unpublish-quiz',
            'list-questions', 'show-question', 'create-question', 'update-question', 'delete-question',
            'list-question_options', 'show-question_option', 'create-question_option',
            'update-question_option', 'delete-question_option',
        ];
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'api']);
        }

        $this->instructor = User::create([
            'name' => 'Instructor',
            'email' => 'instructor@test.com',
            'password' => 'Password123!',
            'phone' => '+963999999999',
            'date_of_birth' => '2000-01-01',
            'gender' => 'male',
        ]);
        $this->instructor->assignRole('super-admin');
        $this->instructor->givePermissionTo($permissions);
        $this->token = auth('api')->login($this->instructor);

        $category = CourseCategory::create([
            'name' => ['en' => 'Tech'],
            'slug' => 'tech',
            'is_active' => true,
        ]);

        $this->course = Course::create([
            'title' => ['en' => 'Laravel Course'],
            'slug' => 'laravel-course',
            'course_category_id' => $category->course_category_id,
            'actual_duration_hours' => 10,
            'status' => 'published',
            'created_by' => $this->instructor->id,
        ]);

        $this->quiz = Quiz::create([
            'instructor_id' => $this->instructor->id,
            'quizable_type' => 'course',
            'quizable_id' => $this->course->course_id,
            'type' => 'quiz',
            'title' => ['en' => 'Final Quiz'],
            'max_score' => 100,
            'passing_score' => 60,
            'status' => 'draft',
            'auto_grade_enabled' => true,
        ]);
    }

    public function test_can_list_quizzes(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/quizzes')
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_cannot_create_quiz_with_passing_score_below_60_percent(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/quizzes', [
                'instructor_id' => $this->instructor->id,
                'quizable_type' => 'course',
                'quizable_id' => $this->course->course_id,
                'type' => 'quiz',
                'title' => ['en' => 'Bad Quiz'],
                'max_score' => 100,
                'passing_score' => 50,
                'status' => 'draft',
                'auto_grade_enabled' => true,
            ])
            ->assertStatus(422);
    }

    public function test_can_show_quiz(): void
    {
        $this->withToken($this->token)
            ->getJson("/api/v1/quizzes/{$this->quiz->id}")
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_can_publish_quiz(): void
    {
        $this->withToken($this->token)
            ->postJson("/api/v1/quizzes/{$this->quiz->id}/publish")
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_can_unpublish_quiz(): void
    {
        $this->quiz->update(['status' => 'published']);

        $this->withToken($this->token)
            ->postJson("/api/v1/quizzes/{$this->quiz->id}/unpublish")
            ->assertOk();
    }

    public function test_can_archive_quiz(): void
    {
        $this->withToken($this->token)
            ->postJson("/api/v1/quizzes/{$this->quiz->id}/archive")
            ->assertOk();
    }

    public function test_can_list_questions(): void
    {
        $this->withToken($this->token)
            ->getJson("/api/v1/questions?quiz_id={$this->quiz->id}")
            ->assertOk();
    }

    public function test_can_create_true_false_question(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/questions', [
                'quiz_id' => $this->quiz->id,
                'type' => 'true_false',
                'question_text' => ['en' => 'Laravel is a PHP framework.'],
                'point' => 5,
                'order_index' => 2,
                'is_required' => true,
            ])
            ->assertStatus(201);
    }

    public function test_can_create_short_answer_question(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/questions', [
                'quiz_id' => $this->quiz->id,
                'type' => 'short_answer',
                'question_text' => ['en' => 'Explain MVC pattern.'],
                'point' => 15,
                'order_index' => 3,
                'is_required' => true,
            ])
            ->assertStatus(201);
    }

    public function test_cannot_create_question_with_duplicate_order_index(): void
    {
        Question::create([
            'quiz_id' => $this->quiz->id,
            'type' => 'multiple_choice',
            'question_text' => ['en' => 'First question'],
            'point' => 10,
            'order_index' => 1,
            'is_required' => true,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/questions', [
                'quiz_id' => $this->quiz->id,
                'type' => 'true_false',
                'question_text' => ['en' => 'Duplicate order'],
                'point' => 5,
                'order_index' => 1,
                'is_required' => true,
            ])
            ->assertStatus(422);
    }

    public function test_can_show_question(): void
    {
        $question = $this->createQuestion();

        $this->withToken($this->token)
            ->getJson("/api/v1/questions/{$question->id}")
            ->assertOk();
    }

    private function createQuestion(string $type = 'multiple_choice'): Question
    {
        static $orderIndex = 1;

        return Question::create([
            'quiz_id' => $this->quiz->id,
            'type' => $type,
            'question_text' => ['en' => 'Test question '.$orderIndex],
            'point' => 10,
            'order_index' => $orderIndex++,
            'is_required' => true,
        ]);
    }
}
