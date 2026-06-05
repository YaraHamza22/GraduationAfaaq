<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\UserMangementModule\Models\User;
use Modules\LearningModule\Models\Course;
use Modules\LearningModule\Models\CourseCategory;
use Modules\LearningModule\Models\Enrollment;
use Modules\AssesmentModule\Models\Quiz;
use Modules\AssesmentModule\Models\Question;
use Modules\AssesmentModule\Models\QuestionOption;
use Modules\AssesmentModule\Models\Attempt;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class AttemptTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $instructor;
    private string $studentToken;
    private string $instructorToken;
    private Quiz $quiz;
    private Question $mcqQuestion;
    private QuestionOption $correctOption;
    private QuestionOption $wrongOption;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['super-admin', 'student', 'instructor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'api']);
        }

        $permissions = [
            'list-attempts', 'show-attempt', 'create-attempt', 'update-attempt',
            'delete-attempt', 'submit-attempt', 'grade-attempt',
            'list-answers', 'show-answer', 'create-answer', 'submit-answer',
        ];
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'api']);
        }

        $this->student = User::create([
            'name'     => 'Student',
            'email'    => 'student@test.com',
            'password' => 'Password123!',
            'phone'         => '+963999999999',
            'date_of_birth'  => '2000-01-01',
            'gender'         => 'male',
        ]);
        $this->student->assignRole('student');
        $this->student->givePermissionTo($permissions);
        $this->studentToken = auth('api')->login($this->student);

        $this->instructor = User::create([
            'name'     => 'Instructor',
            'email'    => 'instructor@test.com',
            'password' => 'Password123!',
            'phone'         => '+963999999999',
            'date_of_birth'  => '2000-01-01',
            'gender'         => 'male',
        ]);
        $this->instructor->assignRole('super-admin');
        $this->instructor->givePermissionTo($permissions);
        $this->instructorToken = auth('api')->login($this->instructor);

        $category = CourseCategory::create([
            'name' => ['en' => 'Tech'], 'slug' => 'tech', 'is_active' => true,
        ]);

        $course = Course::create([
            'title'                => ['en' => 'Laravel'],
            'slug'                 => 'laravel',
            'course_category_id'   => $category->course_category_id,
            'actual_duration_hours'=> 10,
            'status'               => 'published',
            'created_by'           => $this->instructor->id,
        ]);

        $this->quiz = Quiz::create([
            'instructor_id'    => $this->instructor->id,
            'quizable_type'    => 'course',
            'quizable_id'      => $course->course_id,
            'type'             => 'quiz',
            'title'            => ['en' => 'Test Quiz'],
            'max_score'        => 10,
            'passing_score'    => 6,
            'status'           => 'published',
            'auto_grade_enabled' => true,
        ]);

        $this->mcqQuestion = Question::create([
            'quiz_id'       => $this->quiz->id,
            'type'          => 'multiple_choice',
            'question_text' => ['en' => 'What is Laravel?'],
            'point'         => 10,
            'order_index'   => 1,
            'is_required'   => true,
        ]);

        $this->correctOption = QuestionOption::create([
            'question_id' => $this->mcqQuestion->id,
            'option_text' => ['en' => 'A PHP framework'],
            'is_correct'  => true,
        ]);

        $this->wrongOption = QuestionOption::create([
            'question_id' => $this->mcqQuestion->id,
            'option_text' => ['en' => 'A JS library'],
            'is_correct'  => false,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  WORKSPACE
    // ═══════════════════════════════════════════════════════════

    public function test_student_can_open_quiz_workspace(): void
    {
        $this->withToken($this->studentToken)
            ->getJson("/api/v1/attempts/workspace/{$this->quiz->id}")
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_workspace_creates_attempt_if_none_exists(): void
    {
        $this->withToken($this->studentToken)
            ->getJson("/api/v1/attempts/workspace/{$this->quiz->id}")
            ->assertOk();

        $this->assertDatabaseHas('attempts', [
            'quiz_id'    => $this->quiz->id,
            'student_id' => $this->student->id,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  STORE & START
    // ═══════════════════════════════════════════════════════════

    public function test_student_can_create_attempt(): void
    {
        $this->withToken($this->studentToken)
            ->postJson('/api/v1/attempts', [
                'quiz_id'    => $this->quiz->id,
                'student_id' => $this->student->id,
            ])
            ->assertStatus(201);
    }

    public function test_student_can_start_attempt(): void
    {
        $attempt = Attempt::create([
            'quiz_id'        => $this->quiz->id,
            'student_id'     => $this->student->id,
            'attempt_number' => 1,
            'status'         => 'pending',
        ]);

        $this->withToken($this->studentToken)
            ->postJson("/api/v1/attempts/{$attempt->id}/start")
            ->assertStatus(201);

        $this->assertDatabaseHas('attempts', [
            'id'     => $attempt->id,
            'status' => 'in_progress',
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  SUBMIT
    // ═══════════════════════════════════════════════════════════

    public function test_student_can_submit_attempt_with_correct_answer(): void
    {
        $attempt = $this->createInProgressAttempt();

        $this->withToken($this->studentToken)
            ->postJson("/api/v1/attempts/{$attempt->id}/submit", [
                'answers' => [
                    [
                        'question_id'        => $this->mcqQuestion->id,
                        'selected_option_id' => $this->correctOption->id,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('attempts', [
            'id'     => $attempt->id,
            'status' => 'graded',
            'score'  => 10,
        ]);
    }

    public function test_student_can_submit_attempt_with_wrong_answer(): void
    {
        $attempt = $this->createInProgressAttempt();

        $this->withToken($this->studentToken)
            ->postJson("/api/v1/attempts/{$attempt->id}/submit", [
                'answers' => [
                    [
                        'question_id'        => $this->mcqQuestion->id,
                        'selected_option_id' => $this->wrongOption->id,
                    ],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('attempts', [
            'id'    => $attempt->id,
            'score' => 0,
        ]);
    }

    public function test_cannot_submit_attempt_not_in_progress(): void
    {
        $attempt = Attempt::create([
            'quiz_id'        => $this->quiz->id,
            'student_id'     => $this->student->id,
            'attempt_number' => 1,
            'status'         => 'pending',
        ]);

        $this->withToken($this->studentToken)
            ->postJson("/api/v1/attempts/{$attempt->id}/submit", [
                'answers' => [],
            ])
            ->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════
    //  GRADE (manual — short_answer)
    // ═══════════════════════════════════════════════════════════

    public function test_instructor_can_grade_submitted_attempt(): void
    {
        $shortAnswerQuiz = Quiz::create([
            'instructor_id'    => $this->instructor->id,
            'quizable_type'    => 'course',
            'quizable_id'      => $this->quiz->quizable_id,
            'type'             => 'quiz',
            'title'            => ['en' => 'Short Quiz'],
            'max_score'        => 10,
            'passing_score'    => 6,
            'status'           => 'published',
            'auto_grade_enabled' => false,
        ]);

        $shortQuestion = Question::create([
            'quiz_id'       => $shortAnswerQuiz->id,
            'type'          => 'short_answer',
            'question_text' => ['en' => 'Explain MVC'],
            'point'         => 10,
            'order_index'   => 1,
            'is_required'   => true,
        ]);

        $attempt = Attempt::create([
            'quiz_id'        => $shortAnswerQuiz->id,
            'student_id'     => $this->student->id,
            'attempt_number' => 1,
            'status'         => 'submitted',
            'score'          => 0,
        ]);

        \Modules\AssesmentModule\Models\Answer::create([
            'attempt_id'  => $attempt->id,
            'question_id' => $shortQuestion->id,
            'answer_text' => ['en' => 'MVC is a design pattern'],
        ]);

        $this->withToken($this->instructorToken)
            ->postJson("/api/v1/attempts/{$attempt->id}/grade", [
                'answers' => [
                    [
                        'question_id'  => $shortQuestion->id,
                        'earned_score' => 8,
                        'is_correct'   => true,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('attempts', [
            'id'     => $attempt->id,
            'score'  => 8,
            'status' => 'graded',
        ]);
    }

    public function test_cannot_grade_attempt_not_submitted(): void
    {
        $attempt = $this->createInProgressAttempt();

        $this->withToken($this->instructorToken)
            ->postJson("/api/v1/attempts/{$attempt->id}/grade", [
                'answers' => [
                    [
                        'question_id'  => $this->mcqQuestion->id,
                        'earned_score' => 8,
                        'is_correct'   => true,
                    ],
                ],
            ])
            ->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════
    //  LIST & SHOW
    // ═══════════════════════════════════════════════════════════

    public function test_student_can_list_own_attempts(): void
    {
        $this->withToken($this->studentToken)
            ->getJson("/api/v1/attempts?quiz_id={$this->quiz->id}")
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_instructor_can_view_quiz_results(): void
    {
        $this->withToken($this->instructorToken)
            ->getJson("/api/v1/quizzes/{$this->quiz->id}/results")
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    private function createInProgressAttempt(): Attempt
    {
        return Attempt::create([
            'quiz_id'        => $this->quiz->id,
            'student_id'     => $this->student->id,
            'attempt_number' => 1,
            'status'         => 'in_progress',
            'start_at'       => now(),
        ]);
    }
}
