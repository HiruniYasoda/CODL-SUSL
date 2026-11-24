<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\CourseApplicationController;
use App\Http\Controllers\LetterRequestController;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| Courses, Batches, Categories & Applications Routes
|--------------------------------------------------------------------------
*/

// Public courses directory for potential applicants
Route::get('/public/courses', [CourseController::class, 'publicIndex']);

Route::middleware('auth:sanctum')->group(function () {
    // Student course access
    Route::get('/student/courses', [UserController::class, 'getStudentCourses']);
    Route::get('/student/dashboard-overview', [UserController::class, 'getStudentDashboardOverview']);
    Route::get('/student/courses/{courseId}/materials', [UserController::class, 'getCourseMaterials']);
    Route::get('/student/applications', [UserController::class, 'getStudentApplications']);

    // Categories
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // Courses
    Route::get('/courses', [CourseController::class, 'index']);
    Route::post('/courses', [CourseController::class, 'store']);
    Route::get('/courses/{id}', [CourseController::class, 'show']);
    Route::put('/courses/{id}', [CourseController::class, 'update']);
    Route::delete('/courses/{id}', [CourseController::class, 'destroy']);
    Route::get('/courses/{id}/students', [CourseController::class, 'getEnrolledStudents']);
    Route::get('/manage-course/{courseId}', [CourseController::class, 'manageCourseData']);
    Route::post('/courses/{id}/enroll', [CourseController::class, 'enrollStudent']);
    Route::delete('/courses/{id}/students/{studentId}', [CourseController::class, 'unenrollStudent']);

    // Batches
    Route::get('/courses/{id}/batches', [BatchController::class, 'index']);
    Route::get('/courses/{id}/batches/{batchId}/materials', [BatchController::class, 'getMaterials']);
    Route::post('/courses/{id}/batches', [BatchController::class, 'store']);
    Route::put('/courses/{id}/batches/{batchId}', [BatchController::class, 'update']);
    Route::delete('/courses/{id}/batches/{batchId}', [BatchController::class, 'destroy']);
    Route::post('/batches/{batchId}/upload-material', [BatchController::class, 'uploadMaterial']);

    // Course Applications
    Route::get('/course-applications', [CourseApplicationController::class, 'index']);
    Route::get('/course-applications/my', [CourseApplicationController::class, 'myApplications']);
    Route::get('/course-applications/{id}', [CourseApplicationController::class, 'show']);
    Route::post('/course-applications', [CourseApplicationController::class, 'store']);
    Route::post('/course-applications/{id}/approve', [CourseApplicationController::class, 'approve']);
    Route::post('/course-applications/{id}/reject', [CourseApplicationController::class, 'reject']);
    Route::put('/course-applications/{id}/verify-docs', [CourseApplicationController::class, 'updateDocumentsVerified']);
    Route::post('/course-applications/check-nic', [CourseApplicationController::class, 'checkNic']);
    Route::delete('/course-applications/{id}', [CourseApplicationController::class, 'destroy']);

    // Letters
    Route::get('/letter-requests', [LetterRequestController::class, 'index']);
    Route::post('/letter-requests', [LetterRequestController::class, 'store']);
    Route::post('/letter-requests/{id}/approve', [LetterRequestController::class, 'approve']);
    Route::post('/letter-requests/{id}/reject', [LetterRequestController::class, 'reject']);
    Route::patch('/letter-requests/{id}/status', [LetterRequestController::class, 'updateStatus']);
});
