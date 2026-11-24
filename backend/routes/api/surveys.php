<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StudentInterestController;
use App\AI\Controllers\AIAnalyticsController;

/*
|--------------------------------------------------------------------------
| Surveys & Academic Interest Routes
|--------------------------------------------------------------------------
*/

// Public survey submission endpoints (rate-limited for submission integrity)
Route::post('/public/surveys', [AIAnalyticsController::class, 'storeSurvey'])->middleware('throttle:submissions');
Route::post('/student-interests', [StudentInterestController::class, 'store'])->middleware('throttle:submissions');
Route::post('/industry-analysis', [StudentInterestController::class, 'storeIndustry'])->middleware('throttle:submissions');

// Public survey form metadata & configuration
Route::get('/student-interests/config', [StudentInterestController::class, 'getConfig'])->middleware('throttle:survey-configs');
Route::get('/student-interests/teaching-methods', [StudentInterestController::class, 'getTeachingMethods'])->middleware('throttle:survey-configs');
Route::get('/student-interests/university-opportunities', [StudentInterestController::class, 'getUniversityOpportunities'])->middleware('throttle:survey-configs');
Route::get('/industry-analysis/sectors', [StudentInterestController::class, 'getIndustrySectors'])->middleware('throttle:survey-configs');
Route::get('/industry-analysis/config', [StudentInterestController::class, 'getIndustryConfig'])->middleware('throttle:survey-configs');

// Administrative survey configuration management
Route::middleware(['auth:sanctum', 'role:super_admin,director,coordinator,secretary'])->group(function () {
    // Student interest configs
    Route::post('/admin/student-interests/config', [StudentInterestController::class, 'storeConfig']);
    Route::delete('/admin/student-interests/config/{id}', [StudentInterestController::class, 'deleteConfig']);
    Route::post('/admin/student-interests/teaching-methods', [StudentInterestController::class, 'storeTeachingMethod']);
    Route::delete('/admin/student-interests/teaching-methods/{id}', [StudentInterestController::class, 'deleteTeachingMethod']);
    Route::post('/admin/student-interests/university-opportunities', [StudentInterestController::class, 'storeUniversityOpportunity']);
    Route::delete('/admin/student-interests/university-opportunities/{id}', [StudentInterestController::class, 'deleteUniversityOpportunity']);

    // Industry survey configs
    Route::post('/admin/industry-analysis/sectors', [StudentInterestController::class, 'storeIndustrySector']);
    Route::delete('/admin/industry-analysis/sectors/{id}', [StudentInterestController::class, 'deleteIndustrySector']);
    Route::post('/admin/industry-analysis/config', [StudentInterestController::class, 'storeIndustryConfig']);
    Route::delete('/admin/industry-analysis/config/{id}', [StudentInterestController::class, 'deleteIndustryConfig']);
});
