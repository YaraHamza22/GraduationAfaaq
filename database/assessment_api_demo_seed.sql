-- =============================================================================
-- AfaaqEducationalPlatform — MariaDB sequential seed for Assessment Module APIs
-- =============================================================================
-- Prerequisites:
--   1. Run: php artisan migrate
--   2. Run role/permission seeders (so roles `student` and `instructor` exist):
--        php artisan db:seed --class="Modules\UserMangementModule\Database\Seeders\RolesAndPermissions\PermissionSeeder"
--        php artisan db:seed --class="Modules\UserMangementModule\Database\Seeders\RolesAndPermissions\StudentRoleSeeder"
--        php artisan db:seed --class="Modules\UserMangementModule\Database\Seeders\RolesAndPermissions\InstructorRoleSeeder"
--      (or your project's combined DatabaseSeeder that registers these.)
--
-- Demo logins (password for both = "password", Laravel default hash below):
--   Student:     api-student@test.local
--   Instructor:  api-instructor@test.local
--
-- Useful routes after seed:
--   GET  /api/v1/courses/1/assessment-progress?student_id=902
--   POST /api/v1/attempts/1/answers/bulk   (student 902, quiz 1, questions 1–3)
-- =============================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE answers;
TRUNCATE TABLE attempts;
TRUNCATE TABLE question_options;
TRUNCATE TABLE questions;
TRUNCATE TABLE quiz_student;
TRUNCATE TABLE course_certificates;
TRUNCATE TABLE quizzes;
TRUNCATE TABLE enrollment_lesson;
TRUNCATE TABLE enrollments;
TRUNCATE TABLE lessons;
TRUNCATE TABLE units;
TRUNCATE TABLE courses;
TRUNCATE TABLE course_categories;

DELETE FROM model_has_roles
WHERE model_type = 'Modules\\UserMangementModule\\Models\\User'
  AND model_id IN (901, 902);

DELETE FROM students WHERE user_id IN (901, 902);
DELETE FROM users WHERE id IN (901, 902);

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- 1) Users (bcrypt hash = password)
-- -----------------------------------------------------------------------------
INSERT INTO users (
  id, name, email, email_verified_at, password, remember_token,
  phone, date_of_birth, gender, address, deleted_at, created_at, updated_at
) VALUES
(
  901,
  'API Instructor',
  'api-instructor@test.local',
  NOW(),
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  NULL,
  '0500000901',
  '1990-01-01',
  'male',
  NULL,
  NULL,
  NOW(),
  NOW()
),
(
  902,
  'API Student',
  'api-student@test.local',
  NOW(),
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  NULL,
  '0500000902',
  '2001-06-15',
  'female',
  NULL,
  NULL,
  NOW(),
  NOW()
);

-- -----------------------------------------------------------------------------
-- 2) Spatie roles (requires roles table populated — see prerequisites)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO model_has_roles (role_id, model_type, model_id)
SELECT r.id, 'Modules\\UserMangementModule\\Models\\User', 901
FROM roles r
WHERE r.name = 'instructor' AND r.guard_name = 'api'
LIMIT 1;

INSERT IGNORE INTO model_has_roles (role_id, model_type, model_id)
SELECT r.id, 'Modules\\UserMangementModule\\Models\\User', 902
FROM roles r
WHERE r.name = 'student' AND r.guard_name = 'api'
LIMIT 1;

-- -----------------------------------------------------------------------------
-- 3) Student profile (quiz_student FK points here, not users.id)
-- -----------------------------------------------------------------------------
INSERT INTO students (
  id, user_id, education_level, country, bio, specialization, joined_at, deleted_at, created_at, updated_at
) VALUES (
  1,
  902,
  'highschool',
  'SA',
  'Demo student for API tests',
  NULL,
  NOW(),
  NULL,
  NOW(),
  NOW()
);

-- -----------------------------------------------------------------------------
-- 4) Course category + course + unit + lesson (progress service scopes quizzes)
-- -----------------------------------------------------------------------------
INSERT INTO course_categories (
  course_category_id, name, slug, description, is_active, target_audience, created_at, updated_at, deleted_at
) VALUES (
  1,
  '{"en":"STEM","ar":"علوم"}',
  'api-demo-category',
  '{"en":"Demo"}',
  1,
  NULL,
  NOW(),
  NOW(),
  NULL
);

INSERT INTO courses (
  course_id, created_by, course_category_id, title, slug, description, objectives, prerequisites,
  actual_duration_hours, language, status, min_score_to_pass, is_offline_available,
  course_delivery_type, difficulty_level, average_rating, total_ratings, published_at, deleted_at, created_at, updated_at
) VALUES (
  1,
  901,
  1,
  '{"en":"API Demo Course","ar":"دورة تجريبية"}',
  'api-demo-course',
  '{"en":"Assessment API demo"}',
  '{"en":"Pass quizzes"}',
  '{"en":"None"}',
  10,
  'ar',
  'published',
  60.00,
  0,
  'self_paced',
  'beginner',
  0.00,
  0,
  NOW(),
  NULL,
  NOW(),
  NOW()
);

INSERT INTO units (
  unit_id, course_id, unit_order, title, description, actual_duration_minutes, created_at, updated_at, deleted_at
) VALUES (
  1,
  1,
  1,
  '{"en":"Unit 1","ar":"الوحدة 1"}',
  '{"en":"First unit"}',
  60,
  NOW(),
  NOW(),
  NULL
);

INSERT INTO lessons (
  lesson_id, unit_id, lesson_order, title, description, lesson_type, is_required, is_completed,
  actual_duration_minutes, created_at, updated_at, deleted_at
) VALUES (
  1,
  1,
  1,
  '{"en":"Lesson 1","ar":"الدرس 1"}',
  NULL,
  'lecture',
  1,
  0,
  30,
  NOW(),
  NOW(),
  NULL
);

INSERT INTO enrollments (
  enrollment_id, learner_id, course_id, enrolled_by, enrollment_type, enrollment_status,
  enrolled_at, completed_at, progress_percentage, final_grade, created_at, updated_at
) VALUES (
  1,
  902,
  1,
  NULL,
  'self',
  'active',
  NOW(),
  NULL,
  0.00,
  NULL,
  NOW(),
  NOW()
);

-- -----------------------------------------------------------------------------
-- 5) Quizzes: course-level, unit-level, lesson-level (matches CourseQuizProgressService)
-- -----------------------------------------------------------------------------
INSERT INTO quizzes (
  id, instructor_id, title, description, status, type, available_from, due_date,
  quizable_type, quizable_id, max_score, passing_score, auto_grade_enabled, duration_minutes,
  created_at, updated_at, deleted_at
) VALUES
(
  1,
  901,
  '{"en":"Course quiz","ar":"اختبار المقرر"}',
  '{"en":"Main course assessment"}',
  'published',
  'quiz',
  NULL,
  NULL,
  'course',
  1,
  100,
  60,
  1,
  30,
  NOW(),
  NOW(),
  NULL
),
(
  2,
  901,
  '{"en":"Unit quiz","ar":"اختبار الوحدة"}',
  '{"en":"Unit check"}',
  'published',
  'quiz',
  NULL,
  NULL,
  'unit',
  1,
  50,
  30,
  1,
  15,
  NOW(),
  NOW(),
  NULL
),
(
  3,
  901,
  '{"en":"Lesson quiz","ar":"اختبار الدرس"}',
  '{"en":"Lesson check"}',
  'published',
  'quiz',
  NULL,
  NULL,
  'lesson',
  1,
  50,
  30,
  1,
  15,
  NOW(),
  NOW(),
  NULL
);

-- -----------------------------------------------------------------------------
-- 6) Questions (unique pair: quiz_id + order_index)
-- -----------------------------------------------------------------------------
INSERT INTO questions (
  id, quiz_id, type, question_text, point, is_required, order_index, deleted_at, created_at, updated_at
) VALUES
(
  1,
  1,
  'multiple_choice',
  '{"en":"What is 2 + 2?","ar":"كم ٢ زائد ٢؟"}',
  10,
  1,
  1,
  NULL,
  NOW(),
  NOW()
),
(
  2,
  1,
  'true_false',
  '{"en":"Laravel is a PHP framework.","ar":"لارافيل إطار عمل لـ PHP."}',
  10,
  1,
  2,
  NULL,
  NOW(),
  NOW()
),
(
  3,
  1,
  'short_answer',
  '{"en":"Name the number five in English.","ar":"اكتب الرقم خمسة بالإنجليزية."}',
  10,
  1,
  3,
  NULL,
  NOW(),
  NOW()
),
(
  4,
  2,
  'multiple_choice',
  '{"en":"Pick the correct option.","ar":"اختر الإجابة الصحيحة."}',
  5,
  1,
  1,
  NULL,
  NOW(),
  NOW()
),
(
  5,
  3,
  'true_false',
  '{"en":"PHP runs on the server.","ar":"PHP يعمل على الخادم."}',
  5,
  1,
  1,
  NULL,
  NOW(),
  NOW()
);

-- -----------------------------------------------------------------------------
-- 7) Question options (MCQ only)
-- -----------------------------------------------------------------------------
INSERT INTO question_options (
  id, question_id, option_text, is_correct, created_at, updated_at
) VALUES
(
  1,
  1,
  '{"en":"3","ar":"٣"}',
  0,
  NOW(),
  NOW()
),
(
  2,
  1,
  '{"en":"4","ar":"٤"}',
  1,
  NOW(),
  NOW()
),
(
  3,
  4,
  '{"en":"Wrong","ar":"خطأ"}',
  0,
  NOW(),
  NOW()
),
(
  4,
  4,
  '{"en":"Right","ar":"صحيح"}',
  1,
  NOW(),
  NOW()
);

-- -----------------------------------------------------------------------------
-- 8) Assign quizzes to student profile (pivot uses students.id)
-- -----------------------------------------------------------------------------
INSERT INTO quiz_student (id, quiz_id, student_id, created_at, updated_at) VALUES
(1, 1, 1, NOW(), NOW()),
(2, 2, 1, NOW(), NOW()),
(3, 3, 1, NOW(), NOW());

-- -----------------------------------------------------------------------------
-- 9) In-progress attempt for quiz 1 (student = users.id 902)
-- -----------------------------------------------------------------------------
INSERT INTO attempts (
  id, quiz_id, student_id, attempt_number, score, status, is_passed, time_spent_seconds,
  start_at, submitted_at, ends_at, graded_at, graded_by, created_at, updated_at
) VALUES (
  1,
  1,
  902,
  1,
  NULL,
  'in_progress',
  NULL,
  NULL,
  NOW(),
  NULL,
  NULL,
  NULL,
  NULL,
  NOW(),
  NOW()
);

-- -----------------------------------------------------------------------------
-- Reset AUTO_INCREMENT (MariaDB / MySQL)
-- -----------------------------------------------------------------------------
ALTER TABLE course_categories AUTO_INCREMENT = 2;
ALTER TABLE courses AUTO_INCREMENT = 2;
ALTER TABLE units AUTO_INCREMENT = 2;
ALTER TABLE lessons AUTO_INCREMENT = 2;
ALTER TABLE enrollments AUTO_INCREMENT = 2;
ALTER TABLE quizzes AUTO_INCREMENT = 4;
ALTER TABLE questions AUTO_INCREMENT = 6;
ALTER TABLE question_options AUTO_INCREMENT = 5;
ALTER TABLE quiz_student AUTO_INCREMENT = 4;
ALTER TABLE attempts AUTO_INCREMENT = 2;
ALTER TABLE students AUTO_INCREMENT = 2;
ALTER TABLE users AUTO_INCREMENT = 903;

-- =============================================================================
-- Example: POST /api/v1/attempts/1/answers/bulk
-- {
--   "answers": [
--     { "question_id": 1, "selected_option_id": 2 },
--     { "question_id": 2, "boolean_answer": true },
--     { "question_id": 3, "answer_text": { "en": "five", "ar": "خمسة" } }
--   ]
-- }
-- =============================================================================
