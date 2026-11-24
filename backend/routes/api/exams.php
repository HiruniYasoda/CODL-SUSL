<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamApplicationController;
use App\Http\Controllers\ExamResultController;
use App\Http\Controllers\PostponementRequestController;
use App\Http\Controllers\ReattemptRequestController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| Examination & Evaluation Workflow Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // Exams
    Route::get('/courses/{id}/exams', [ExamController::class, 'index']);
    Route::post('/courses/{id}/exams', [ExamController::class, 'store']);
    Route::put('/exams/{id}', [ExamController::class, 'update']);
    Route::delete('/exams/{id}', [ExamController::class, 'destroy']);
    Route::post('/exams/upload-timetable', [ExamController::class, 'uploadTimetable']);

    // Student examinations overview
    Route::get('/student/courses/{courseId}/examinations-data', [UserController::class, 'getStudentExaminationsData']);

    // Exam Applications
    Route::get('/exam-applications', [ExamApplicationController::class, 'index']);
    Route::post('/exam-applications', [ExamApplicationController::class, 'store']);
    Route::put('/exam-applications/{id}', [ExamApplicationController::class, 'update']);
    Route::delete('/exam-applications/{id}', [ExamApplicationController::class, 'destroy']);
    Route::get('/student/exam-applications', [ExamApplicationController::class, 'myApplications']);

    // Postponement Requests
    Route::get('/postponement-requests', [PostponementRequestController::class, 'index']);
    Route::post('/postponement-requests', [PostponementRequestController::class, 'store']);
    Route::put('/postponement-requests/{id}', [PostponementRequestController::class, 'update']);
    Route::delete('/postponement-requests/{id}', [PostponementRequestController::class, 'destroy']);
    Route::get('/student/postponement-requests', [PostponementRequestController::class, 'myRequests']);

    // Reattempt Requests
    Route::get('/reattempt-requests', [ReattemptRequestController::class, 'index']);
    Route::post('/reattempt-requests', [ReattemptRequestController::class, 'store']);
    Route::put('/reattempt-requests/{id}', [ReattemptRequestController::class, 'update']);
    Route::delete('/reattempt-requests/{id}', [ReattemptRequestController::class, 'destroy']);
    Route::get('/student/reattempt-requests', [ReattemptRequestController::class, 'myRequests']);

    // Exam Results
    Route::get('/exam-results', [ExamResultController::class, 'index']);
    Route::post('/exam-results', [ExamResultController::class, 'store']);
    Route::get('/exam-results/my', [ExamResultController::class, 'myResults']);
    Route::get('/exam-results/exam/{examId}', [ExamResultController::class, 'getByExam']);

    // Announcements
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::post('/announcements', [AnnouncementController::class, 'store']);
    Route::delete('/announcements/{id}', [AnnouncementController::class, 'destroy']);
});
