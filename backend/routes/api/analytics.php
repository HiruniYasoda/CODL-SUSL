<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AIAnalysisController;
use App\AI\Controllers\AIAnalyticsController;

/*
|--------------------------------------------------------------------------
| AI Analytics & NLP Intelligence Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'role:super_admin,director,coordinator,secretary'])->group(function () {
    // Single course AI analysis endpoint
    Route::post('/admin/ai-analysis', [AIAnalysisController::class, 'analyze']);

    // Program Intelligence Dashboard
    Route::get('/admin/ai-analytics/programs', [AIAnalyticsController::class, 'getPrograms']);
    Route::get('/admin/ai-analytics/global-overview', [AIAnalyticsController::class, 'getGlobalOverview']);
    Route::get('/admin/ai-analytics/common/overview', [AIAnalyticsController::class, 'getCommonOverview']);
    Route::get('/admin/ai-analytics/common/drilldown', [AIAnalyticsController::class, 'getCommonDrilldown']);
    Route::post('/admin/ai-analytics/sync-sheet', [AIAnalyticsController::class, 'syncGoogleSheet']);

    // Course-specific drill-down & NLP analytics
    Route::get('/admin/ai-analytics/{courseId}/overview', [AIAnalyticsController::class, 'getOverview']);
    Route::get('/admin/ai-analytics/{courseId}/student-demand', [AIAnalyticsController::class, 'getStudentInterest']);
    Route::get('/admin/ai-analytics/{courseId}/industry-demand', [AIAnalyticsController::class, 'getIndustryGap']);
    Route::get('/admin/ai-analytics/{courseId}/recommendations', [AIAnalyticsController::class, 'getRecommendations']);
    Route::get('/admin/ai-analytics/{courseId}/skill-gap', [AIAnalyticsController::class, 'getSkillGap']);
    Route::get('/admin/ai-analytics/{courseId}/emerging-technologies', [AIAnalyticsController::class, 'getEmergingTechnologies']);
    Route::get('/admin/ai-analytics/{courseId}/export', [AIAnalyticsController::class, 'exportCSV']);
    Route::get('/admin/ai-analytics/{courseId}/academic-entry', [AIAnalyticsController::class, 'getAcademicEntryRequirements']);

    // Survey aggregations & geographic demographics
    Route::get('/admin/ai-analytics/surveys', [AIAnalyticsController::class, 'getSurveys']);
    Route::post('/admin/ai-analytics/surveys', [AIAnalyticsController::class, 'storeSurvey']);
    Route::get('/admin/ai-analytics/geography', [AIAnalyticsController::class, 'getGeographyData']);
    Route::get('/admin/ai-analytics/geography/skills', [AIAnalyticsController::class, 'getGeographySkills']);
    Route::get('/admin/ai-analytics/industry/skills', [AIAnalyticsController::class, 'getIndustrySkills']);
    Route::get('/admin/ai-analytics/university-opportunities', [AIAnalyticsController::class, 'getUniversityOpportunities']);
});
