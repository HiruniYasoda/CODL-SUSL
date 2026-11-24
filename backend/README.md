# CODL Backend: Architecture & API Foundation

The backend for CODL-SUSL is powered by **Laravel 11**, designed with clean separation of concerns, enterprise role-based authorization, modular routing, and a decoupled dual-database architecture.

---

## Directory Structure

```
backend/
├── app/
│   ├── AI/                   # Machine learning & NLP curriculum gap analysis
│   │   ├── Controllers/      # Analytics dashboard endpoints
│   │   └── Models/           # Analytics entities (StudentInterest, IndustryRequirement, etc.)
│   ├── Http/
│   │   ├── Controllers/      # Thin HTTP controllers (request parsing & response dispatching)
│   │   ├── Middleware/       # RoleMiddleware, ThrottleMiddleware, Sanctum guards
│   │   └── Requests/         # Form request validation rules
│   ├── Jobs/                 # Asynchronous background tasks (NLP pipeline rebuilding)
│   ├── Models/               # Operational domain entities (Course, User, Batch, Exam, etc.)
│   └── Services/             # Business domain logic (CourseService, etc.)
├── database/
│   ├── migrations/           # Multi-database schema migration files
│   └── seeders/              # Database population scripts
├── routes/
│   ├── api/                  # Domain-partitioned modular route definitions
│   │   ├── admin.php         # Administration, system settings & backup controls
│   │   ├── analytics.php     # AI & NLP program analytics endpoints
│   │   ├── auth.php          # Authentication & profile management
│   │   ├── courses.php       # Courses, categories, batches, applications
│   │   ├── exams.php         # Examination lifecycles, timetables, results
│   │   └── surveys.php       # Public student & industry surveys
│   └── api.php               # Master route registry
└── tests/
    └── Feature/              # Comprehensive automated feature tests
```

---

## Core Technical Features

### 1. Decoupled Service Architecture
To avoid bloated controllers, domain operations are encapsulated within specialized services:
- **`App\Services\CourseService`**: Coordinates course creation, nested semester/subject synchronizations, student enrollments, activity logging, and versioned cache bust operations.

### 2. Dual Database Connections
Configured in `config/database.php`:
- **`mysql` (Default)**: Handles user authentication, courses, batches, exams, results, and administrative workflows.
- **`analytics`**: Houses high-volume survey submissions, skill interest catalogs, and computed coverage metrics.

### 3. Fail-Closed reCAPTCHA Verification
All public survey endpoints (`/api/student-interests`, `/api/industry-analysis`) employ anti-bot verification that strictly denies requests if validation fails or if the upstream verification service times out.

### 4. Protected Table Inspector
The administrative table explorer (`DatabaseTableController`) enforces an explicit `ALLOWED_TABLES` allowlist (`categories`, `announcements`, `survey_interests_config`, etc.), actively rejecting access to security-critical tables such as `users`, `personal_access_tokens`, or `system_settings`.

---

## Running Backend Tests

```bash
php artisan test
```

Tests run in-memory using SQLite (`:memory:`) as configured in `phpunit.xml`.
