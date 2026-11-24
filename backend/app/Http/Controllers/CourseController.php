<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Models\Course;
use App\Models\Batch;
use App\Services\CourseService;

class CourseController extends Controller
{
    protected CourseService $courseService;

    public function __construct(CourseService $courseService)
    {
        $this->courseService = $courseService;
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Course::with([
            'category',
            'secretary',
            'coordinator',
            'batches:id,course_id,name,start_date,registration_deadline,max_enrollments,subtitle,status'
        ])->withCount(['batches', 'students']);
        
        if ($user && $user->role === 'secretary') {
            $query->where('secretary_id', $user->id);
        } elseif ($user && $user->role === 'coordinator') {
            $query->where('coordinator_id', $user->id);
        } elseif ($user && $user->role === 'lecturer') {
            $assignedSubjectCourseIds = DB::table('batch_subject_instructor')
                ->join('batches', 'batches.id', '=', 'batch_subject_instructor.batch_id')
                ->where('batch_subject_instructor.instructor_id', $user->id)
                ->pluck('batches.course_id')
                ->toArray();
                
            $assignedBatchInstructorCourseIds = Batch::where('instructor_id', $user->id)
                ->pluck('course_id')
                ->toArray();
                
            $assignedCourseIds = array_unique(array_merge($assignedSubjectCourseIds, $assignedBatchInstructorCourseIds));
            
            $query->whereIn('id', $assignedCourseIds);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['super_admin', 'director'])) {
            return response()->json(['message' => 'Unauthorized to create courses.'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string',
            'code' => 'required|string|unique:courses',
            'level' => 'required|string',
            'department' => 'nullable|string',
            'duration' => 'required|string',
            'intake_status' => 'required|string',
            'max_students' => 'nullable|integer',
            'secretary_id' => 'nullable|exists:users,id',
            'coordinator_id' => 'nullable|exists:users,id',
            'category_id' => 'nullable|exists:categories,id',
            'semesters' => 'nullable|array',
            'subjects' => 'nullable|array',
        ]);

        try {
            $course = $this->courseService->createCourse($validated, $user);
            return response()->json($course->load(['semesters.subjects', 'subjects', 'secretary', 'coordinator']), 201);
        } catch (ValidationException $e) {
            Log::error('Validation Failed: ', $e->errors());
            throw $e;
        } catch (\Exception $e) {
            Log::error('Course Creation Error: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $course = Course::with(['category', 'secretary', 'coordinator', 'semesters.subjects', 'subjects'])->findOrFail($id);
        return response()->json($course);
    }

    public function update(Request $request, $id)
    {
        $course = Course::findOrFail($id);
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }
        
        if ($user->role === 'coordinator') {
            if ($course->coordinator_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized to edit this course.'], 403);
            }
        } elseif ($user->role !== 'super_admin' && $user->role !== 'director') {
            return response()->json(['message' => 'Unauthorized to edit this course.'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string',
            'code' => 'required|string|unique:courses,code,' . $id,
            'level' => 'required|string',
            'department' => 'nullable|string',
            'duration' => 'required|string',
            'intake_status' => 'required|string',
            'max_students' => 'nullable|integer',
            'secretary_id' => 'nullable|exists:users,id',
            'coordinator_id' => 'nullable|exists:users,id',
            'category_id' => 'nullable|exists:categories,id',
            'semesters' => 'nullable|array',
            'subjects' => 'nullable|array',
        ]);

        try {
            $updatedCourse = $this->courseService->updateCourse($course, $validated, $user);
            return response()->json($updatedCourse->load(['semesters.subjects', 'subjects', 'secretary', 'coordinator', 'category']));
        } catch (ValidationException $e) {
            Log::error('Validation Failed: ', $e->errors());
            throw $e;
        } catch (\Exception $e) {
            Log::error('Course Update Error: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['super_admin', 'director'])) {
            return response()->json(['message' => 'Unauthorized to delete courses.'], 403);
        }

        $course = Course::findOrFail($id);
        $this->courseService->deleteCourse($course, $user);

        return response()->json(['message' => 'Course deleted successfully']);
    }

    public function publicIndex()
    {
        Batch::where('status', 'Upcoming')
            ->whereNotNull('registration_deadline')
            ->where('registration_deadline', '<', now()->toDateString())
            ->update(['status' => 'Active']);

        $courses = Course::whereHas('batches', function ($q) {
                $q->where('status', 'Upcoming')
                  ->where(function ($query) {
                      $query->whereNull('registration_deadline')
                            ->orWhere('registration_deadline', '>=', now()->toDateString());
                  });
            })
            ->with(['batches' => function ($q) {
                $q->where('status', 'Upcoming')
                  ->where(function ($query) {
                      $query->whereNull('registration_deadline')
                            ->orWhere('registration_deadline', '>=', now()->toDateString());
                  })
                  ->orderBy('start_date', 'desc');
            }])
            ->get();

        return response()->json($courses);
    }

    public function getEnrolledStudents($id)
    {
        $course = Course::findOrFail($id);
        return response()->json($this->courseService->mapEnrolledStudents($course));
    }

    public function enrollStudent(Request $request, $id)
    {
        $validated = $request->validate([
            'student_id' => 'required|string',
            'batch' => 'nullable|string',
        ]);
        
        $course = Course::findOrFail($id);
        $studentData = $this->courseService->enrollStudent($course, $validated['student_id'], $validated['batch'] ?? null, $request->user());

        return response()->json([
            'message' => 'Student enrolled successfully',
            'student' => $studentData,
        ]);
    }

    public function unenrollStudent(Request $request, $id, $studentId)
    {
        $course = Course::findOrFail($id);
        $this->courseService->unenrollStudent($course, $studentId, $request->user());

        return response()->json(['message' => 'Student suspended/unenrolled successfully']);
    }

    public function manageCourseData(Request $request, $courseId)
    {
        $user = $request->user();
        $forceRefresh = $request->query('refresh') === 'true' || $request->has('refresh');

        $data = $this->courseService->getManageCourseData((int)$courseId, $user, $forceRefresh);

        return response()->json($data);
    }

    public static function clearManageCourseCache($courseId)
    {
        app(CourseService::class)->clearManageCourseCache((int)$courseId);
    }
}
