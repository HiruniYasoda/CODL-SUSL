<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Modular route architecture separating concerns into domain-specific files:
| - auth: Authentication, profile, password reset
| - surveys: Public and admin interest survey endpoints & config
| - courses: Courses, categories, batches, materials & enrollment
| - exams: Examination workflows, results, postponements, reattempts
| - admin: Administrative controls, user management, backups, table inspections
| - analytics: AI Analytics pipeline, NLP skill gap analysis, recommendations
|
*/

require __DIR__ . '/api/auth.php';
require __DIR__ . '/api/surveys.php';
require __DIR__ . '/api/courses.php';
require __DIR__ . '/api/exams.php';
require __DIR__ . '/api/admin.php';
require __DIR__ . '/api/analytics.php';
