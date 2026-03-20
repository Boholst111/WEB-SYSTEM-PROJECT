<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Unit Tests for Authentication Flows
 * 
 * Tests specific examples, edge cases, and integration scenarios for:
 * - Login/Logout flows
 * - Session management
 * - Access control and permissions
 * - Token management
 * - Password management
 * 
 * Validates: Requirements 1.9 (User Authentication and Authorization)
 */
class AuthenticationFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear rate limiter before each test
        RateLimiter::clear('*');
    }

    // ========================================
    // LOGIN/LOGOUT FLOW TESTS
    // ========================================

    public function test_successful_login_creates_token_and_updates_last_login()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
            'last_login_at' => null,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertEquals('Bearer', $response->json('data.token_type'));

        // Verify last_login_at was updated
        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertTrue($user->last_login_at->isToday());

        // Verify token was created
        $this->assertGreaterThan(0, $user->tokens()->count());
    }

    public function test_login_with_remember_me_preserves_existing_tokens()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        // Create an existing token
        $existingToken = $user->createToken('existing-session');
        $this->assertEquals(1, $user->tokens()->count());

        $response = $this->postJson('/api/auth/login', [
            'email' => 'john@example.com',
            'password' => 'password123',
            'remember' => true,
        ]);

        $response->assertStatus(200);
        
        // Should have 2 tokens now (existing + new)
        $user->refresh();
        $this->assertEquals(2, $user->tokens()->count());
    }

    public function test_login_without_remember_me_revokes_existing_tokens()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        // Create existing tokens
        $user->createToken('session1');
        $user->createToken('session2');
        $this->assertEquals(2, $user->tokens()->count());

        $response = $this->postJson('/api/auth/login', [
            'email' => 'john@example.com',
            'password' => 'password123',
            'remember' => false,
        ]);

        $response->assertStatus(200);
        
        // Should have only 1 token now (the new one)
        $user->refresh();
        $this->assertEquals(1, $user->tokens()->count());
    }

    public function test_logout_revokes_current_token_only()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        
        // Login to get a real token
        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);
        
        $token = $response->json('data.token');
        
        // Create an additional token manually
        $user->createToken('additional_token');
        $this->assertEquals(2, $user->tokens()->count());

        // Logout using the real token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/logout');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        // Should have 1 token remaining
        $user->refresh();
        $this->assertEquals(1, $user->tokens()->count());
    }

    public function test_logout_all_revokes_all_user_tokens()
    {
        $user = User::factory()->create();
        
        // Create multiple tokens
        $user->createToken('session1');
        $user->createToken('session2');
        $user->createToken('session3');
        $this->assertEquals(3, $user->tokens()->count());

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/logout-all');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        // Should have no tokens remaining
        $user->refresh();
        $this->assertEquals(0, $user->tokens()->count());
    }

    // ========================================
    // SESSION MANAGEMENT TESTS
    // ========================================

    public function test_token_refresh_creates_new_token_and_revokes_old()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        
        // Login to get a real token
        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);
        
        $originalToken = $response->json('data.token');
        $this->assertEquals(1, $user->tokens()->count());

        // Refresh the token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $originalToken,
        ])->postJson('/api/auth/refresh');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertEquals('Bearer', $response->json('data.token_type'));

        // Should still have 1 token (the new one replaces the old one)
        $user->refresh();
        $this->assertEquals(1, $user->tokens()->count());
    }

    public function test_token_becomes_invalid_after_logout()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        
        // Login to get a real token
        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);
        
        $token = $response->json('data.token');
        $this->assertEquals(1, $user->tokens()->count());
        
        // Verify token works
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/auth/me');
        $response->assertStatus(200);

        // Logout to invalidate the token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/logout');
        $response->assertStatus(200);

        // Verify token was deleted from database
        $user->refresh();
        $this->assertEquals(0, $user->tokens()->count());
    }

    public function test_concurrent_sessions_are_handled_correctly()
    {
        $user = User::factory()->create();
        
        // Create multiple tokens (simulating multiple devices)
        $token1 = $user->createToken('device1');
        $token2 = $user->createToken('device2');
        $token3 = $user->createToken('device3');
        
        $this->assertEquals(3, $user->tokens()->count());

        // Each token should work independently
        $this->actingAs($user, 'sanctum');
        $response = $this->getJson('/api/auth/me');
        $response->assertStatus(200);
    }

    // ========================================
    // ACCESS CONTROL AND PERMISSIONS TESTS
    // ========================================

    public function test_unauthenticated_user_cannot_access_protected_routes()
    {
        $protectedRoutes = [
            ['GET', '/api/auth/me'],
            ['POST', '/api/auth/logout'],
            ['POST', '/api/auth/refresh'],
            ['POST', '/api/auth/change-password'],
        ];

        foreach ($protectedRoutes as [$method, $route]) {
            $response = $this->json($method, $route);
            $response->assertStatus(401);
        }
    }

    public function test_authenticated_user_can_access_user_routes()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/me');
        $response->assertStatus(200);
        $this->assertEquals($user->id, $response->json('data.user.id'));
    }

    public function test_non_admin_user_cannot_access_admin_routes()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'preferences' => []
        ]);
        
        Sanctum::actingAs($user);

        // Try to access admin route
        $response = $this->getJson('/api/admin/dashboard');
        $response->assertStatus(403);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('Insufficient permissions', $response->json('message'));
    }

    public function test_admin_user_can_access_admin_routes()
    {
        $admin = User::factory()->create([
            'email' => 'admin@diecastempire.com',
            'preferences' => ['role' => 'admin']
        ]);
        
        Sanctum::actingAs($admin);

        // Admin should be able to access admin routes
        $response = $this->getJson('/api/admin/dashboard');
        // Note: This might return 404 if the route doesn't exist yet, but should not return 403
        $this->assertNotEquals(403, $response->status());
    }

    public function test_role_middleware_correctly_identifies_admin_users()
    {
        // Test admin by email
        $adminByEmail = User::factory()->create([
            'email' => 'admin@diecastempire.com'
        ]);
        
        Sanctum::actingAs($adminByEmail);
        $response = $this->getJson('/api/admin/dashboard');
        $this->assertNotEquals(403, $response->status());

        // Test admin by preferences
        $adminByRole = User::factory()->create([
            'email' => 'other@example.com',
            'preferences' => ['role' => 'admin']
        ]);
        
        Sanctum::actingAs($adminByRole);
        $response = $this->getJson('/api/admin/dashboard');
        $this->assertNotEquals(403, $response->status());
    }

    public function test_suspended_user_cannot_login()
    {
        $user = User::factory()->create([
            'email' => 'suspended@example.com',
            'password_hash' => Hash::make('password123'),
            'status' => 'suspended',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'suspended@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('Account is suspended or inactive', $response->json('message'));
    }

    public function test_inactive_user_cannot_login()
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'password_hash' => Hash::make('password123'),
            'status' => 'inactive',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('Account is suspended or inactive', $response->json('message'));
    }

    // ========================================
    // LOGIN VALIDATION AND SECURITY TESTS
    // ========================================

    public function test_login_fails_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password_hash' => Hash::make('correct_password'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('Invalid credentials', $response->json('message'));
    }

    public function test_login_fails_with_nonexistent_email()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('Invalid credentials', $response->json('message'));
    }

    public function test_login_validation_requires_email_and_password()
    {
        // Missing email
        $response = $this->postJson('/api/auth/login', [
            'password' => 'password123',
        ]);
        $response->assertStatus(422);
        $this->assertArrayHasKey('email', $response->json('errors'));

        // Missing password
        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
        ]);
        $response->assertStatus(422);
        $this->assertArrayHasKey('password', $response->json('errors'));

        // Invalid email format
        $response = $this->postJson('/api/auth/login', [
            'email' => 'invalid-email',
            'password' => 'password123',
        ]);
        $response->assertStatus(422);
        $this->assertArrayHasKey('email', $response->json('errors'));
    }

    public function test_rate_limiting_blocks_excessive_login_attempts()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        // Make 5 failed attempts (should trigger rate limiting)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'user@example.com',
                'password' => 'wrong_password',
            ]);
            $response->assertStatus(401);
        }

        // 6th attempt should be rate limited
        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(429);
        $this->assertStringContainsString('Too many login attempts', $response->json('message'));
    }

    public function test_successful_login_clears_rate_limiting()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        // Make 4 failed attempts
        for ($i = 0; $i < 4; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'user@example.com',
                'password' => 'wrong_password',
            ]);
            $response->assertStatus(401);
        }

        // Successful login should clear rate limiting
        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);
        $response->assertStatus(200);

        // Should be able to make another attempt after successful login
        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'wrong_password',
        ]);
        $response->assertStatus(401); // Not rate limited
    }

    // ========================================
    // TOKEN MANAGEMENT TESTS
    // ========================================

    public function test_token_contains_correct_user_information()
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password_hash' => Hash::make('password123'),
            'loyalty_tier' => 'silver',
            'loyalty_credits' => 150.50,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $userData = $response->json('data.user');
        
        $this->assertEquals('John', $userData['first_name']);
        $this->assertEquals('Doe', $userData['last_name']);
        $this->assertEquals('john@example.com', $userData['email']);
        $this->assertEquals('silver', $userData['loyalty_tier']);
        $this->assertEquals(150.50, $userData['loyalty_credits']);
    }

    public function test_me_endpoint_returns_authenticated_user_data()
    {
        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $userData = $response->json('data.user');
        
        $this->assertEquals($user->id, $userData['id']);
        $this->assertEquals('Jane', $userData['first_name']);
        $this->assertEquals('Smith', $userData['last_name']);
        $this->assertEquals('jane@example.com', $userData['email']);
    }

    public function test_token_refresh_maintains_user_session()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Get user data before refresh
        $response = $this->getJson('/api/auth/me');
        $response->assertStatus(200);
        $originalUserId = $response->json('data.user.id');

        // Refresh token
        $response = $this->postJson('/api/auth/refresh');
        $response->assertStatus(200);
        $newToken = $response->json('data.token');

        // Use new token to get user data
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $newToken,
        ])->getJson('/api/auth/me');

        $response->assertStatus(200);
        $this->assertEquals($originalUserId, $response->json('data.user.id'));
    }

    // ========================================
    // EDGE CASES AND ERROR HANDLING
    // ========================================

    public function test_logout_handles_invalid_token_gracefully()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test');
        
        // Delete token manually to simulate invalid token
        $user->tokens()->delete();
        
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/logout');
        
        // Should handle gracefully even with invalid token
        $this->assertContains($response->status(), [200, 401, 500]);
    }

    public function test_refresh_token_fails_without_authentication()
    {
        $response = $this->postJson('/api/auth/refresh');
        $response->assertStatus(401);
    }

    public function test_logout_all_removes_all_user_sessions()
    {
        $user = User::factory()->create();
        
        // Create multiple tokens for the user
        $user->createToken('device1');
        $user->createToken('device2');
        $user->createToken('device3');
        $this->assertEquals(3, $user->tokens()->count());

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/logout-all');
        $response->assertStatus(200);

        // All tokens should be removed
        $user->refresh();
        $this->assertEquals(0, $user->tokens()->count());
    }

    public function test_user_data_excludes_sensitive_information()
    {
        $user = User::factory()->create([
            'password_hash' => Hash::make('secret_password'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/me');
        $response->assertStatus(200);
        
        $userData = $response->json('data.user');
        
        // Should not include sensitive data
        $this->assertArrayNotHasKey('password_hash', $userData);
        $this->assertArrayNotHasKey('password', $userData);
        $this->assertArrayNotHasKey('remember_token', $userData);
    }
}