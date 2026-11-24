<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class SanitizedBackupExceptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_failure_does_not_leak_database_or_path_details()
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        SystemSetting::create([
            'institution_name' => 'CODL',
            'university_name' => 'SUSL',
            'backup_frequency' => 'daily',
            'backup_retention' => 30,
        ]);

        // Force a DB query failure during backup execution to trigger the catch block
        DB::shouldReceive('connection')->andThrow(new \Exception('SQLSTATE[HY000] [2002] Connection refused to root@127.0.0.1:3306 on /var/run/mysqld/mysqld.sock'));

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson('/api/admin/backups/run');

        $response->assertStatus(500);

        // Security check: Verify raw SQL / path / connection error is sanitized
        $responseContent = $response->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $responseContent);
        $this->assertStringNotContainsString('root@127.0.0.1', $responseContent);
        $this->assertStringNotContainsString('/var/run/mysqld', $responseContent);

        $response->assertJson([
            'message' => 'Database backup failed. Please check the system logs for details.'
        ]);
    }
}
