<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Property-Based Test for User Authentication Security
 * 
 * **Feature: diecast-empire, Property 5: User authentication security**
 * **Validates: Requirements 1.9**
 * 
 * Property: For any authentication attempt, unauthorized users should be denied 
 * access to protected resources, and authenticated users should only access 
 * resources they own or have permission to view.
 */
class AuthenticationSecurityPropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Property Test: Unauthorized users are denied access to protected resources
     * 
     * This test verifies that unauthenticated requests to protected endpoints
     * consistently return 401 Unauthorized status across all protected routes.
     */
    public function test_property_unauthorized_users_denied_access()
    {
        // Test multiple protected endpoints without authentication
        $protectedEndpoints = [
            ['GET', '/api/user'],
            ['POST', '/api/auth/logout'],
            ['POST', '/api/auth/refresh'],
            ['GET', '/api/auth/me'],
            ['POST', '/api/auth/change-password'],
        ];

        foreach ($protectedEndpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint);
            
            $this->assertEquals(401, $response->status(), 
                "Endpoint {$method} {$endpoint} should deny unauthorized access");
        }
    }

    /**
     * Property Test: Authenticated users can only access their own resources
     * 
     * This test verifies that authenticated users cannot access resources
     * belonging to other users, maintaining proper authorization boundaries.
     */
    public function test_property_authenticated_users_access_own_resources_only()
    {
        // Create two different users
        $user1 = User::factory()->create(['email' => 'user1@example.com']);
        $user2 = User::factory()->create(['email' => 'user2@example.com']);

        // Authenticate as user1
        Sanctum::actingAs($user1);

        // Test that user1 can access their own profile
        $response = $this->getJson('/api/auth/me');
        $this->assertEquals(200, $response->status());
        $this->assertEquals($user1->id, $response->json('data.user.id'));
        $this->assertEquals($user1->email, $response->json('data.user.email'));

        // Test that the returned user data belongs to the authenticated user
        $this->assertNotEquals($user2->id, $response->json('data.user.id'));
        $this->assertNotEquals($user2->email, $response->json('data.user.email'));
    }

    /**
     * Property Test: Token-based authentication maintains session integrity
     * 
     * This test verifies that authentication tokens properly identify users
     * and that token revocation immediately denies access.
     */
    public function test_property_token_authentication_integrity()
    {
        $user = User::factory()->create();
        
        // Create a token for the user
        $token = $user->createToken('test-token')->plainTextToken;

        // Test that the token provides access
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/auth/me');

        $this->assertEquals(200, $response->status());
        $this->assertEquals($user->id, $response->json('data.user.id'));

        // Revoke the token
        $user->tokens()->delete();

        // Clear any cached authentication
        $this->app['auth']->forgetGuards();

        // Test that revoked token denies access
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/auth/me');

        $this->assertEquals(401, $response->status());
    }

    /**
     * Property Test: Password authentication security
     * 
     * This test verifies that password-based authentication properly validates
     * credentials and denies access with invalid passwords.
     */
    public function test_property_password_authentication_security()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password_hash' => Hash::make('correct-password'),
            'status' => 'active',
        ]);

        // Test various invalid password scenarios
        $invalidCredentials = [
            ['email' => 'test@example.com', 'password' => 'wrong-password'],
            ['email' => 'test@example.com', 'password' => ''],
            ['email' => 'test@example.com', 'password' => 'correct-passwor'], // Almost correct
            ['email' => 'test@example.com', 'password' => 'CORRECT-PASSWORD'], // Wrong case
            ['email' => 'wrong@example.com', 'password' => 'correct-password'], // Wrong email
        ];

        foreach ($invalidCredentials as $credentials) {
            $response = $this->postJson('/api/auth/login', $credentials);
            
            $this->assertContains($response->status(), [401, 422], 
                "Invalid credentials should be rejected: " . json_encode($credentials));
            
            if ($response->status() === 401) {
                $this->assertFalse($response->json('success'));
            }
        }

        // Test that correct credentials work
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'correct-password',
        ]);

        $this->assertEquals(200, $response->status());
        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('data.token'));
    }

    /**
     * Property Test: Account status enforcement
     * 
     * This test verifies that inactive or suspended accounts are properly
     * denied access regardless of correct credentials.
     */
    public function test_property_account_status_enforcement()
    {
        $inactiveStatuses = ['inactive', 'suspended'];
        
        foreach ($inactiveStatuses as $status) {
            $user = User::factory()->create([
                'email' => "test-{$status}@example.com",
                'password_hash' => Hash::make('password123'),
                'status' => $status,
            ]);

            // Test that inactive/suspended accounts cannot login
            $response = $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'password123',
            ]);

            $this->assertEquals(403, $response->status(), 
                "Account with status '{$status}' should be denied access");
            $this->assertFalse($response->json('success'));
        }

        // Test that active account can login
        $activeUser = User::factory()->create([
            'email' => 'active@example.com',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'active@example.com',
            'password' => 'password123',
        ]);

        $this->assertEquals(200, $response->status());
        $this->assertTrue($response->json('success'));
    }

    /**
     * Property Test: Session isolation between users
     * 
     * This test verifies that user sessions are properly isolated and
     * one user's authentication doesn't affect another user's session.
     */
    public function test_property_session_isolation()
    {
        $user1 = User::factory()->create(['email' => 'user1@example.com']);
        $user2 = User::factory()->create(['email' => 'user2@example.com']);

        // Create tokens for both users
        $token1 = $user1->createToken('user1-token')->plainTextToken;
        $token2 = $user2->createToken('user2-token')->plainTextToken;

        // Test that each token returns the correct user
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1,
        ])->getJson('/api/auth/me');

        $this->assertEquals(200, $response1->status());
        $this->assertEquals($user1->id, $response1->json('data.user.id'));

        // Clear authentication state between requests
        $this->app['auth']->forgetGuards();

        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token2,
        ])->getJson('/api/auth/me');

        $this->assertEquals(200, $response2->status());
        $this->assertEquals($user2->id, $response2->json('data.user.id'));
        
        // Verify users cannot see each other's data
        $this->assertNotEquals($user2->id, $response1->json('data.user.id'));
        $this->assertNotEquals($user1->id, $response2->json('data.user.id'));

        // Test that revoking one user's token doesn't affect the other
        $user1->tokens()->delete();
        $this->app['auth']->forgetGuards(); // Clear auth cache

        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1,
        ])->getJson('/api/auth/me');

        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token2,
        ])->getJson('/api/auth/me');

        $this->assertEquals(401, $response1->status()); // User1 denied
        $this->assertEquals(200, $response2->status()); // User2 still has access
        $this->assertEquals($user2->id, $response2->json('data.user.id'));
    }

    /**
     * Property Test: Rate limiting protection
     * 
     * This test verifies that authentication endpoints are protected
     * against brute force attacks through rate limiting.
     */
    public function test_property_rate_limiting_protection()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password_hash' => Hash::make('correct-password'),
            'status' => 'active',
        ]);

        // Attempt multiple failed logins to trigger rate limiting
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ]);

            if ($i < 5) {
                // First 5 attempts should return 401
                $this->assertEquals(401, $response->status());
            } else {
                // 6th attempt should be rate limited
                $this->assertEquals(429, $response->status());
                $this->assertStringContainsString('Too many', $response->json('message'));
            }
        }
    }
}