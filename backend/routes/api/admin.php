<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\DatabaseTableController;
use App\Http\Controllers\BackupController;

/*
|--------------------------------------------------------------------------
| Administration, Settings, Diagnostics & Operations Routes
|--------------------------------------------------------------------------
*/

// Public system settings read (e.g. branding, institution name, maintenance flag)
Route::get('/admin/system-settings', [SystemSettingController::class, 'getSettings']);

Route::middleware('auth:sanctum')->group(function () {
    // Shared Staff & Admin diagnostics and stats
    Route::middleware('role:super_admin,director,coordinator,secretary,lecturer')->group(function () {
        Route::get('/admin/track-students/search', [UserController::class, 'searchStudents'])->middleware('throttle:api');
        Route::get('/admin/track-students/{id}/details', [UserController::class, 'getStudentTrackingDetails']);
        Route::get('/admin/stats', [StatsController::class, 'getAdminStats']);
        Route::get('/admin/dashboard-full', [StatsController::class, 'getFullDashboardData']);
        Route::get('/admin/activity-logs', [StatsController::class, 'getActivityLogs']);
        Route::get('/admin/health-stats', [StatsController::class, 'getSystemHealthStats']);
    });

    // Staff/Course Management and System Settings
    Route::middleware('role:super_admin,director,coordinator,secretary')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::post('/admin/system-settings', [SystemSettingController::class, 'updateSettings']);
        Route::post('/admin/system-settings/logo', [SystemSettingController::class, 'uploadLogo']);
    });

    // Super Admin & Director User Management
    Route::middleware('role:super_admin,director')->group(function () {
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
    });

    // Super Admin Only: Protected Database Table Inspection & Backups
    Route::middleware('role:super_admin')->group(function () {
        // Table inspector (protected by strict allowlist)
        Route::get('/admin/tables', [DatabaseTableController::class, 'getTables']);
        Route::get('/admin/tables/{tableName}', [DatabaseTableController::class, 'getTableData']);
        Route::delete('/admin/tables/{tableName}/{id}', [DatabaseTableController::class, 'deleteRecord']);

        // Database backups (sanitized error responses)
        Route::get('/admin/backups', [BackupController::class, 'index']);
        Route::post('/admin/backups/run', [BackupController::class, 'run']);
        Route::get('/admin/backups/download/{filename}', [BackupController::class, 'download']);
        Route::delete('/admin/backups/{filename}', [BackupController::class, 'destroy']);
    });
});
