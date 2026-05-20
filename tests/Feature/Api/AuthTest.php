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
        $user = User::factory()->create([
            'email'     => 'test@oms.dz',
            'password'  => bcrypt('password123'),
            'is_active' => true,
        ]);
        $user->assignRole('super_admin');

        $this->postJson('/api/auth/login', ['email' => 'test@oms.dz', 'password' => 'password123'])
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['token', 'user' => ['id']]]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['email' => 'test@oms.dz']);
        $user->assignRole('super_admin');

        $this->postJson('/api/auth/login', ['email' => 'test@oms.dz', 'password' => 'wrong123'])
            ->assertStatus(401);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email'     => 'inactive@oms.dz',
            'password'  => bcrypt('password'),
            'is_active' => false,
        ]);
        $user->assignRole('super_admin');

        $this->postJson('/api/auth/login', ['email' => 'inactive@oms.dz', 'password' => 'password'])
            ->assertStatus(403);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/logout')
            ->assertStatus(200);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }
}
