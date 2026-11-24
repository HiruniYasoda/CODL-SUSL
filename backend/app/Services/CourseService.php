<?php

namespace App\Services;

use App\Models\Course;
use App\Models\User;
use App\Models\Batch;
use App\Models\Exam;
use App\Models\Announcement;
use App\Models\CourseApplication;
use App\Models\ExamApplication;
use App\Models\PostponementRequest;
use App\Models\ReattemptRequest;
use App\Models\ActivityLog;
use App\Jobs\ProcessAnalyticsPipelineJob;
use App\AI\Models\AnalyticsCache;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CourseService
{
    /**
     * Create a new course along with its curriculum (semesters & subjects),
     * write activity log, and trigger the AI analytics pipeline.
     */
    public function createCourse(array $data, User $actor): Course
    {
        return DB::transaction(function () use ($data, $actor) {
            $courseData = Arr::except($data, ['semesters', 'subjects']);
            $course = Course::create($courseData);

            if (!empty($data['semesters'])) {
                foreach ($data['semesters'] as $semData) {
                    $semester = $course->semesters()->create(['name' => $semData['name']]);
                    if (!empty($semData['subjects'])) {
                        foreach ($semData['subjects'] as $subData) {
                            $semester->subjects()->create([
                                'course_id' => $course->id,
                                'name' => $subData['name'],
                                'code' => $subData['code'] ?? ($course->code . '-' . rand(100, 999)),
                                'credits' => $subData['credits'],
                            ]);
                        }
                    }
                }
            }

            if (!empty($data['subjects'])) {
                foreach ($data['subjects'] as $subData) {
                    $course->subjects()->create([
                        'name' => $subData['name'],
                        'code' => $subData['code'] ?? ($course->code . '-' . rand(100, 999)),
                        'credits' => $subData['credits'],
                    ]);
                }
            }

            ActivityLog::log($actor->id, 'Created new Course', "Course: {$course->title} ({$course->code})", 'course');

            // Dispatch AI analytics pipeline and invalidate global caches
            $this->invalidateAnalyticsCachesAndDispatch($course->id, true);

            return $course;
        });
    }

    /**
     * Update an existing course and its curriculum structure.
     */
    public function updateCourse(Course $course, array $data, User $actor): Course
    {
        return DB::transaction(function () use ($course, $data, $actor) {
            $courseData = Arr::except($data, ['semesters', 'subjects']);
            $course->update($courseData);

            if (array_key_exists('semesters', $data)) {
                foreach ($course->semesters as $oldSem) {
                    $oldSem->subjects()->delete();
                }
                $course->semesters()->delete();

                if (is_array($data['semesters'])) {
                    foreach ($data['semesters'] as $semData) {
                        $semester = $course->semesters()->create(['name' => $semData['name']]);
                        if (!empty($semData['subjects'])) {
                            foreach ($semData['subjects'] as $subData) {
                                $semester->subjects()->create([
                                    'course_id' => $course->id,
                                    'name' => $subData['name'],
                                    'code' => $subData['code'] ?? ($course->code . '-' . rand(100, 999)),
                                    'credits' => $subData['credits'],
                                ]);
                            }
                        }
                    }
                }
            }

            if (array_key_exists('subjects', $data)) {
                $course->subjects()->delete();

                if (is_array($data['subjects'])) {
                    foreach ($data['subjects'] as $subData) {
                        $course->subjects()->create([
                            'name' => $subData['name'],
                            'code' => $subData['code'] ?? ($course->code . '-' . rand(100, 999)),
                            'credits' => $subData['credits'],
                        ]);
                    }
                }
            }

            $this->clearManageCourseCache($course->id);

            ActivityLog::log($actor->id, 'Updated Course configuration', "Course: {$course->title} ({$course->code})", 'course');

            $this->invalidateAnalyticsCachesAndDispatch($course->id, false);

            return $course;
        });
    }

    /**
     * Delete a course and invalidate its associated analytics caches.
     */
    public function deleteCourse(Course $course, User $actor): void
    {
        $courseId = $course->id;
        $courseTitle = $course->title;
        $courseCode = $course->code;

        $course->delete();

        ActivityLog::log($actor->id, 'Deleted Course', "Course: {$courseTitle} ({$courseCode})", 'course');

        try {
            AnalyticsCache::where('scope_type', 'program')->where('scope_id', $courseId)->delete();
            $this->clearGlobalOverviewCaches();
        } catch (\Exception $e) {
            Log::error('Failed to clear AnalyticsCache on course delete: ' . $e->getMessage());
        }
    }

    /**
     * Enroll a student into a course and optional batch.
     */
    public function enrollStudent(Course $course, string $studentIdentifier, ?string $batch, ?User $actor): array
    {
        return DB::transaction(function () use ($course, $studentIdentifier, $batch, $actor) {
            $user = User::where('student_number', $studentIdentifier)
                ->orWhere('id', $studentIdentifier)
                ->orWhere('email', $studentIdentifier)
                ->firstOrFail();

            $course->students()->syncWithoutDetaching([$user->id => ['batch' => $batch]]);
            $this->clearManageCourseCache($course->id);

            if ($actor) {
                ActivityLog::log(
                    $actor->id,
                    "Enrolled student {$user->full_name}",
                    "Student: " . ($user->student_number ?: $user->id) . " in course {$course->code} (" . ($batch ?: 'No Batch') . ")",
                    'user'
                );
            }

            return [
                'id' => $user->student_number ?? (string)$user->id,
                'name' => $user->full_name,
                'displayName' => $user->display_name,
                'email' => $user->email,
                'phone' => $user->phone ?? '077 123 4567',
                'enrollmentDate' => now()->toDateString(),
                'status' => ucfirst($user->status) ?: 'Active',
                'payment' => 'Verified',
                'batch' => $batch,
            ];
        });
    }

    /**
     * Unenroll/suspend a student from a course.
     */
    public function unenrollStudent(Course $course, string $studentIdentifier, ?User $actor): void
    {
        DB::transaction(function () use ($course, $studentIdentifier, $actor) {
            $user = User::where('student_number', $studentIdentifier)
                ->orWhere('id', $studentIdentifier)
                ->firstOrFail();

            $course->students()->detach($user->id);
            $this->clearManageCourseCache($course->id);

            if ($actor) {
                ActivityLog::log(
                    $actor->id,
                    "Unenrolled student {$user->full_name}",
                    "Student: " . ($user->student_number ?: $user->id) . " from course {$course->code}",
                    'user'
                );
            }
        });
    }

    /**
     * Bust the cache tag/key for course management data.
     */
    public function clearManageCourseCache(int $courseId): void
    {
        try {
            $version = (int) Cache::get("manage_course_version_{$courseId}", 1);
            Cache::put("manage_course_version_{$courseId}", $version + 1, 3600);
        } catch (\Exception $e) {
            Cache::put("manage_course_version_{$courseId}", time(), 3600);
        }
    }

    /**
     * Fetch aggregated course management data with role-based filtering and caching.
     */
    public function getManageCourseData(int $courseId, ?User $user, bool $forceRefresh = false): array
    {
        $isLecturer = $user && $user->role === 'lecturer';
        $cacheSuffix = $isLecturer ? "lecturer_{$user->id}" : "admin";

        if ($forceRefresh) {
            $this->clearManageCourseCache($courseId);
        }

        $version = Cache::get("manage_course_version_{$courseId}", 1);
        $cacheKey = "manage_course_data_{$courseId}_{$cacheSuffix}_v{$version}";

        return Cache::remember($cacheKey, 600, function () use ($courseId, $user) {
            return $this->getManageCourseDataRaw($courseId, $user);
        });
    }

    /**
     * Assemble raw course management data from relational models.
     */
    public function getManageCourseDataRaw(int $courseId, ?User $user): array
    {
        $course = Course::with(['category', 'secretary', 'coordinator', 'semesters.subjects', 'subjects'])->findOrFail($courseId);
        $enrolledStudents = $this->mapEnrolledStudents($course);

        $lecturers = User::where('role', 'lecturer')
            ->select('id', 'full_name', 'email')
            ->get();

        $batches = $this->getBatchesForManageCourse($courseId, $user);
        $exams = Exam::where('course_id', $courseId)->latest()->get();
        $announcements = Announcement::where('course_id', $courseId)->latest()->get();
        $requests = $this->getCourseRequests($courseId);

        return array_merge([
            'course' => $course,
            'enrolled_students' => $enrolledStudents,
            'student_users' => [],
            'lecturers' => $lecturers,
            'batches' => $batches,
            'exams' => $exams,
            'announcements' => $announcements,
        ], $requests);
    }

    /**
     * Map course enrolled students with presentation formatting.
     */
    public function mapEnrolledStudents(Course $course)
    {
        return $course->students()->get()->map(function ($student) {
            return [
                'id' => $student->student_number ?? (string)$student->id,
                'real_id' => $student->id,
                'student_number' => $student->student_number,
                'name' => $student->full_name,
                'displayName' => $student->display_name,
                'email' => $student->email,
                'phone' => $student->phone ?? '077 123 4567',
                'enrollmentDate' => $student->pivot->created_at ? $student->pivot->created_at->toDateString() : now()->toDateString(),
                'status' => ucfirst($student->status) ?: 'Active',
                'payment' => 'Verified',
                'batch' => $student->pivot->batch,
            ];
        });
    }

    /**
     * Query and resolve instructor information for course batches.
     */
    public function getBatchesForManageCourse(int $courseId, ?User $user)
    {
        Batch::where('course_id', $courseId)
            ->where('status', 'Upcoming')
            ->whereNotNull('registration_deadline')
            ->where('registration_deadline', '<', now()->toDateString())
            ->update(['status' => 'Active']);

        $batchQuery = Batch::where('course_id', $courseId)
            ->select([
                'id',
                'course_id',
                'instructor_id',
                'name',
                'start_date',
                'registration_deadline',
                'max_enrollments',
                'subtitle',
                'status',
                'created_at',
                'updated_at'
            ]);

        if ($user && $user->role === 'lecturer') {
            $batchQuery->where(function ($q) use ($user) {
                $q->whereHas('subjects', function ($subQ) use ($user) {
                    $subQ->where('batch_subject_instructor.instructor_id', $user->id);
                })->orWhere('instructor_id', $user->id);
            });
        }

        $batches = $batchQuery->with([
            'subjects' => function ($query) {
                $query->withPivot('instructor_id');
            },
            'instructor:id,full_name'
        ])
        ->latest()
        ->get();

        $instructorIds = [];
        foreach ($batches as $batch) {
            foreach ($batch->subjects as $subject) {
                if ($subject->pivot->instructor_id) {
                    $instructorIds[] = $subject->pivot->instructor_id;
                }
            }
        }

        $instructors = [];
        if (!empty($instructorIds)) {
            $instructors = User::whereIn('id', array_unique($instructorIds))
                ->select('id', 'full_name')
                ->get()
                ->keyBy('id');
        }

        foreach ($batches as $batch) {
            foreach ($batch->subjects as $subject) {
                $instId = $subject->pivot->instructor_id;
                $subject->pivot->instructor_name = ($instId && isset($instructors[$instId]))
                    ? $instructors[$instId]->full_name
                    : null;
            }
        }

        return $batches;
    }

    /**
     * Gather pending course requests.
     */
    public function getCourseRequests(int $courseId): array
    {
        return [
            'enrollment_requests' => CourseApplication::where('course_id', $courseId)->with('user:id,full_name,display_name,email,student_number')->get(),
            'exam_applications' => ExamApplication::where('course_id', $courseId)->with('user:id,full_name,display_name,email,student_number')->get(),
            'postponement_requests' => PostponementRequest::where('course_id', $courseId)->with('user:id,full_name,display_name,email,student_number')->get(),
            'reattempt_requests' => ReattemptRequest::where('course_id', $courseId)->with(['user:id,full_name,display_name,email,student_number', 'subject'])->get(),
        ];
    }

    /**
     * Helper to invalidate AI analytics program and global overview caches.
     */
    protected function invalidateAnalyticsCachesAndDispatch(int $courseId, bool $sync = false): void
    {
        try {
            AnalyticsCache::where('scope_type', 'program')
                ->where('scope_id', $courseId)
                ->delete();

            $this->clearGlobalOverviewCaches();

            if ($sync) {
                ProcessAnalyticsPipelineJob::dispatchSync($courseId);
            } else {
                ProcessAnalyticsPipelineJob::dispatch($courseId);
            }
        } catch (\Exception $e) {
            Log::error("Failed to run AI analytics pipeline for course {$courseId}: " . $e->getMessage());
        }
    }

    /**
     * Clear global AI analytics overview caches.
     */
    protected function clearGlobalOverviewCaches(): void
    {
        Cache::forget('ai_analytics_global_overview');
        Cache::forget('ai_analytics_geography_data');
        Cache::forget('ai_analytics_common_overview');
    }
}
