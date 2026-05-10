<?php

namespace Modules\LearningModule\Services;

use App\Traits\CachesQueries;
use App\Traits\HelperTrait;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\LearningModule\Models\Lesson;
use Modules\LearningModule\Models\Unit;

/**
 * Service class for managing lesson business logic.
 * Handles lesson creation, updates, ordering, deletion, and various lesson operations.
 */
class LessonService
{
    use HelperTrait, CachesQueries;

    /**
     * Enrollment service instance.
     *
     * @var EnrollmentService
     */
    protected EnrollmentService $enrollmentService;

    /**
     * Create a new lesson service instance.
     *
     * @param EnrollmentService $enrollmentService
     */
    public function __construct(EnrollmentService $enrollmentService)
    {
        $this->enrollmentService = $enrollmentService;
    }
    /**
     * Create a new lesson.
     *
     * @param Unit $unit
     * @param array $data
     * @return Lesson
     * @throws Exception
     */
    public function create(Unit $unit, array $data): ?Lesson
    {
        try {
            $data['unit_id'] = $unit->unit_id;

            // Set lesson_order if not provided (set to next available order)
            if (!isset($data['lesson_order'])) {
                $data['lesson_order'] = $this->getNextOrder(Lesson::class, 'unit_id', $unit->unit_id, 'lesson_order');
            } else {
                // Validate order uniqueness
                $this->validateOrder(Lesson::class, 'unit_id', $unit->unit_id, $data['lesson_order'], 'lesson_order', 'lesson_id', null, 'Lesson');
            }

            $lesson = Lesson::create($data);

            $this->syncLessonMedia($lesson, $data);

            $this->clearLessonCache($lesson, $unit);

            Log::info("Lesson created", [
                'lesson_id' => $lesson->lesson_id,
                'unit_id' => $unit->unit_id,
                'title' => $lesson->title,
                'lesson_order' => $lesson->lesson_order,
            ]);

            return $lesson;
        } catch (Exception $e) {
            Log::error("Failed to create lesson", [
                'unit_id' => $unit->unit_id,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Update an existing lesson.
     *
     * @param Lesson $lesson
     * @param array $data
     * @return Lesson
     * @throws Exception
     */
    public function update(Lesson $lesson, array $data): ?Lesson
    {
        try {
            // Handle order change
            if (isset($data['lesson_order']) && $data['lesson_order'] != $lesson->lesson_order) {
                $this->validateOrder(Lesson::class, 'unit_id', $lesson->unit_id, $data['lesson_order'], 'lesson_order', 'lesson_id', $lesson->lesson_id, 'Lesson');
            }

            $lesson->update($data);

            $this->syncLessonMedia($lesson, $data);

            $this->clearLessonCache($lesson);

            Log::info("Lesson updated", [
                'lesson_id' => $lesson->lesson_id,
                'updated_fields' => array_keys($data),
            ]);

            return $lesson->fresh();
        } catch (Exception $e) {
            Log::error("Failed to update lesson", [
                'lesson_id' => $lesson->lesson_id,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Delete a lesson (soft delete).
     *
     * @param Lesson $lesson
     * @return bool
     * @throws Exception
     */
    public function delete(Lesson $lesson): bool
    {
        try {
            return DB::transaction(function () use ($lesson) {
                $lessonId = $lesson->lesson_id;
                $lessonTitle = $lesson->title;
                $unitId = $lesson->unit_id;
                $deleted = $lesson->delete();

                if ($deleted) {
                    // Clear lesson and unit cache after deletion
                    $this->clearLessonCache($lesson);

                    Log::info("Lesson deleted", [
                        'lesson_id' => $lessonId,
                        'title' => $lessonTitle,
                        'unit_id' => $unitId,
                    ]);
                }

                return $deleted;
            });
        } catch (Exception $e) {
            Log::error("Failed to delete lesson", [
                'lesson_id' => $lesson->lesson_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Reorder lessons within a unit.
     *
     * @param array<int|string,int> $lessonOrders lesson_id => lesson_order (1-based positions)
     */
    public function reorder(Unit $unit, array $lessonOrders): bool
    {
        $orders = array_values($lessonOrders);
        if (count($orders) !== count(array_unique($orders))) {
            Log::warning('Attempted to reorder lessons with duplicate orders', [
                'unit_id' => $unit->unit_id,
            ]);

            return false;
        }

        try {
            DB::transaction(function () use ($unit, $lessonOrders) {
                // Two-phase update avoids unique(unit_id, lesson_order) violations when swapping positions.
                foreach (array_keys($lessonOrders) as $lessonId) {
                    Lesson::where('unit_id', $unit->unit_id)
                        ->where('lesson_id', (int) $lessonId)
                        ->update(['lesson_order' => -abs((int) $lessonId)]);
                }

                foreach ($lessonOrders as $lessonId => $order) {
                    Lesson::where('unit_id', $unit->unit_id)
                        ->where('lesson_id', (int) $lessonId)
                        ->update(['lesson_order' => (int) $order]);
                }
            });

            $this->flushTags(['lessons', "unit.{$unit->unit_id}"]);

            Log::info('Lessons reordered', [
                'unit_id' => $unit->unit_id,
                'lessons_count' => count($lessonOrders),
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to reorder lessons', [
                'unit_id' => $unit->unit_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Get lessons for a unit.
     *
     * @param Unit $unit
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLessonsByUnit(Unit $unit, array $filters = [])
    {
        $query = Lesson::where('unit_id', $unit->unit_id);

        // Apply filters
        if (isset($filters['include_deleted']) && $filters['include_deleted']) {
            $query->withTrashed();
        }

        // Order by lesson_order
        $query->orderBy('lesson_order', 'asc');

        return $query->get();
    }

    /**
     * Get lesson by ID.
     *
     * @param int $lessonId
     * @return Lesson
     * @throws Exception
     */
    public function getById(int $lessonId): ?Lesson
    {
        $lesson = Lesson::find($lessonId);

        if (!$lesson) {
            Log::warning("Lesson not found", [
                'lesson_id' => $lessonId,
            ]);
            return null;
        }

        return $lesson;
    }

    /**
     * Get lesson duration.
     *
     * @param Lesson $lesson
     * @return int Duration in minutes
     */
    public function getDuration(Lesson $lesson): int
    {
        return $lesson->actual_duration_minutes ?? 0;
    }

    /**
     * Get lesson count for a unit.
     *
     * @param Unit $unit
     * @return int
     */
    public function getLessonCount(Unit $unit): int
    {
        return Lesson::where('unit_id', $unit->unit_id)->count();
    }

    /**
     * Clear lesson related cache.
     * Uses Redis tags for efficient bulk invalidation.
     *
     * @param Lesson $lesson
     * @param Unit|null $unit Optional unit to clear unit cache
     * @return void
     */
    protected function clearLessonCache(Lesson $lesson, ?Unit $unit = null): void
    {
        // Use Redis tags for efficient bulk invalidation
        $this->flushTags(['lessons', "lesson.{$lesson->lesson_id}"]);
        $unit = $unit ?? $lesson->unit;
        if ($unit) {
            $this->flushTags(["unit.{$unit->unit_id}"]);
        }
    }

    /**
     * Persist any uploaded media files to the matching lesson collections.
     *
     * Single-file collections (`video`, `pdf`, `presentation`) accept one
     * upload per request. Multi-file collections (`audio`, `attachments`)
     * accept arrays of uploads. MIME validation is enforced on the model.
     *
     * @param Lesson $lesson
     * @param array<string, mixed> $data
     */
    protected function syncLessonMedia(Lesson $lesson, array $data): void
    {
        $singleCollections = ['video', 'pdf', 'presentation'];
        foreach ($singleCollections as $collection) {
            if (isset($data[$collection]) && is_object($data[$collection]) && method_exists($data[$collection], 'isValid') && $data[$collection]->isValid()) {
                $lesson->clearMediaCollection($collection);
                $lesson->addMedia($data[$collection])->toMediaCollection($collection);
            }
        }

        $multiCollections = ['audio', 'attachments'];
        foreach ($multiCollections as $collection) {
            if (!isset($data[$collection]) || !is_array($data[$collection])) {
                continue;
            }

            foreach ($data[$collection] as $file) {
                if (is_object($file) && method_exists($file, 'isValid') && $file->isValid()) {
                    $lesson->addMedia($file)->toMediaCollection($collection);
                }
            }
        }
    }
}
