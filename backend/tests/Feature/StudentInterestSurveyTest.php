<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\AI\Models\StudentInterest;
use App\AI\Models\IndustryRequirement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class StudentInterestSurveyTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_student_interest_submission_is_persisted_to_database()
    {
        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        Config::set('services.recaptcha.secret', null);

        $payload = [
            'email' => 'student.test@example.com',
            'whatsapp_no' => '0771234567',
            'education_level' => 'Undergraduate',
            'province' => 'Western',
            'district' => 'Colombo',
            'primary_field' => 'Data Science',
            'primary_skills' => 'Python, SQL, Machine Learning',
            'primary_teaching_methods' => 'Practical Workshops',
            'primary_theory_practical' => 4,
            'secondary_field' => 'Cybersecurity',
            'secondary_skills' => 'Network Security',
            'secondary_teaching_methods' => 'Hands-on Labs',
            'secondary_theory_practical' => 5,
            'third_field' => 'Cloud Computing',
            'third_skills' => 'AWS, Docker',
            'third_teaching_methods' => 'Projects',
            'third_theory_practical' => 3,
            'university_opportunities' => 'Internships',
            'new_program_suggestion' => 'AI Engineering MSc',
        ];

        $response = $this->postJson('/api/student-interests', $payload);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Student academic interests recorded successfully.'
                 ]);

        $this->assertDatabaseHas('student_interests', [
            'email' => 'student.test@example.com',
            'education_level' => 'Undergraduate',
            'primary_interest' => 'Data Science',
        ], 'analytics');
    }

    public function test_student_interest_validation_fails_when_required_fields_are_missing()
    {
        $response = $this->postJson('/api/student-interests', [
            'email' => 'incomplete@example.com',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['education_level', 'province', 'district', 'primary_field']);
    }

    public function test_recaptcha_verification_failure_rejects_submission()
    {
        Config::set('services.recaptcha.secret', 'valid_test_secret_key');

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => false,
                'score' => 0.1,
            ], 200),
        ]);

        $payload = [
            'email' => 'bot@example.com',
            'education_level' => 'High School',
            'province' => 'Central',
            'district' => 'Kandy',
            'primary_field' => 'Robotics',
            'primary_skills' => 'Arduino',
            'primary_teaching_methods' => 'Lectures',
            'primary_theory_practical' => 3,
            'recaptcha_token' => 'invalid_bot_token',
        ];

        $response = $this->postJson('/api/student-interests', $payload);

        $response->assertStatus(422)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Anti-bot verification failed. Please try again.'
                 ]);

        $this->assertDatabaseMissing('student_interests', [
            'email' => 'bot@example.com',
        ], 'analytics');
    }

    public function test_recaptcha_network_error_fails_closed_and_denies_submission()
    {
        Config::set('services.recaptcha.secret', 'valid_test_secret_key');

        // Simulate network timeout or service unavailability
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
            },
        ]);

        $payload = [
            'email' => 'user.failclosed@example.com',
            'education_level' => 'Undergraduate',
            'province' => 'Southern',
            'district' => 'Galle',
            'primary_field' => 'Software Engineering',
            'primary_skills' => 'Java, Spring',
            'primary_teaching_methods' => 'Lab Sessions',
            'primary_theory_practical' => 4,
            'recaptcha_token' => 'some_token',
        ];

        $response = $this->postJson('/api/student-interests', $payload);

        // Security check: must fail closed with 503 instead of silently passing
        $response->assertStatus(503)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Anti-bot verification service unavailable. Please try again later.'
                 ]);

        $this->assertDatabaseMissing('student_interests', [
            'email' => 'user.failclosed@example.com',
        ], 'analytics');
    }

    public function test_valid_industry_analysis_submission_is_persisted_to_database()
    {
        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        Config::set('services.recaptcha.secret', null);

        $payload = [
            'company_name' => 'Acme Tech Solutions',
            'industry_sector' => 'Information Technology',
            'organization_size' => '51-200',
            'primary_academic_field' => 'Cloud Architecture',
            'secondary_academic_field' => 'DevOps',
            'third_academic_field' => 'AI/ML',
            'required_skills' => 'Kubernetes, Terraform, Go',
            'academic_practices' => 'Agile Scrum',
            'minimum_qualification' => 'BSc',
            'minimum_degree_result' => 'Second Upper',
            'certification_importance' => 4,
            'emerging_fields' => 'Generative AI',
            'new_program_suggestion' => 'Cloud Native Systems',
            'graduate_skill_gaps' => 'Production deployment experience',
            'additional_recommendations' => 'Incorporate industry internships',
        ];

        $response = $this->postJson('/api/industry-analysis', $payload);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Industry requirements submitted successfully.'
                 ]);

        $this->assertDatabaseHas('industry_requirements', [
            'company_name' => 'Acme Tech Solutions',
            'industry_sector' => 'Information Technology',
            'primary_academic_field' => 'Cloud Architecture',
        ], 'analytics');
    }
}
