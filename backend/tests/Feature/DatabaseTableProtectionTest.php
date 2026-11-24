<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DatabaseTableProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_tables()
    {
        $response = $this->getJson('/api/admin/tables');
        $response->assertStatus(401);
    }

    public function test_non_super_admin_cannot_access_tables()
    {
        $lecturer = User::factory()->create(['role' => 'lecturer']);

        $response = $this->actingAs($lecturer, 'sanctum')
                         ->getJson('/api/admin/tables');

        $response->assertStatus(403);
    }

    public function test_super_admin_table_list_only_contains_allowlisted_tables()
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($admin, 'sanctum')
                         ->getJson('/api/admin/tables');

        $response->assertStatus(200);

        $tableNames = collect($response->json())->pluck('name')->toArray();

        // Sensitive tables must never appear in administrative table listing
        $this->assertNotContains('users', $tableNames);
        $this->assertNotContains('personal_access_tokens', $tableNames);
        $this->assertNotContains('system_settings', $tableNames);
        $this->assertNotContains('activity_logs', $tableNames);
        $this->assertNotContains('migrations', $tableNames);
    }

    public function test_super_admin_cannot_read_arbitrary_protected_table_data()
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        // Attempt to read protected 'users' table
        $response = $this->actingAs($admin, 'sanctum')
                         ->getJson('/api/admin/tables/users');

        $response->assertStatus(403)
                 ->assertJson([
                     'message' => 'Access denied. The requested table is protected by system security policies.'
                 ]);
    }

    public function test_super_admin_cannot_delete_from_protected_table()
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $victim = User::factory()->create(['role' => 'student']);

        // Attempt to delete user via generic table delete endpoint
        $response = $this->actingAs($admin, 'sanctum')
                         ->deleteJson("/api/admin/tables/users/{$victim->id}");

        $response->assertStatus(403)
                 ->assertJson([
                     'message' => 'Access denied. Deletion is restricted for this table.'
                 ]);

        $this->assertDatabaseHas('users', ['id' => $victim->id]);
    }

    public function test_super_admin_can_read_and_delete_from_allowlisted_table()
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $category = Category::create([
            'name' => 'Engineering',
            'description' => 'Engineering programs'
        ]);

        // Reading allowlisted 'categories' table
        $readResponse = $this->actingAs($admin, 'sanctum')
                             ->getJson('/api/admin/tables/categories');

        $readResponse->assertStatus(200)
                     ->assertJsonFragment(['name' => 'Engineering']);

        // Deleting from allowlisted table
        $deleteResponse = $this->actingAs($admin, 'sanctum')
                               ->deleteJson("/api/admin/tables/categories/{$category->id}");

        $deleteResponse->assertStatus(200)
                       ->assertJson(['message' => 'Record deleted successfully.']);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
