<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'super_admin']);
        Role::create(['name' => 'well_manager']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $role = Role::where('name', 'super_admin')->first();
        User::factory()->create([
            'email'     => 'test@oms.dz',
            'password'  => bcrypt('password123'),
            'role_id'   => $role->id,
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/login', ['email' => 'test@oms.dz', 'password' => 'password123'])
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['token', 'user' => ['id', 'role']]]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $role = Role::where('name', 'super_admin')->first();
        User::factory()->create(['email' => 'test@oms.dz', 'role_id' => $role->id]);

        $this->postJson('/api/auth/login', ['email' => 'test@oms.dz', 'password' => 'wrong'])
            ->assertStatus(401);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $role = Role::where('name', 'super_admin')->first();
        User::factory()->create([
            'email'     => 'inactive@oms.dz',
            'password'  => bcrypt('password'),
            'role_id'   => $role->id,
            'is_active' => false,
        ]);

        $this->postJson('/api/auth/login', ['email' => 'inactive@oms.dz', 'password' => 'password'])
            ->assertStatus(403);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $role = Role::where('name', 'super_admin')->first();
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_user_can_logout(): void
    {
        $role = Role::where('name', 'super_admin')->first();
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/logout')
            ->assertStatus(200);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }
}
