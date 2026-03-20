<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_password_reset()
    {
        $user = User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'john@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password reset link sent to your email address',
            ]);

        // Verify token was created
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'john@example.com',
        ]);
    }

    public function test_password_reset_fails_with_invalid_email()
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_password_reset_validation()
    {
        $response = $this->postJson('/api/auth/forgot-password', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_reset_password_with_valid_token()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password_hash' => Hash::make('oldpassword'),
        ]);

        // Create password reset token
        $token = Password::createToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'john@example.com',
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password reset successfully. Please login with your new password.',
            ]);

        // Verify password was changed
        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password_hash));

        // Verify all tokens were revoked
        $this->assertEquals(0, $user->tokens()->count());
    }

    public function test_password_reset_fails_with_invalid_token()
    {
        $user = User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'john@example.com',
            'token' => 'invalid-token',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired reset token',
            ]);
    }

    public function test_password_reset_validation_errors()
    {
        $response = $this->postJson('/api/auth/reset-password', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'token', 'password']);
    }

    public function test_password_reset_fails_with_password_mismatch()
    {
        $user = User::factory()->create(['email' => 'john@example.com']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'john@example.com',
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'differentpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_authenticated_user_can_change_password()
    {
        $user = User::factory()->create([
            'password_hash' => Hash::make('oldpassword'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/change-password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password changed successfully',
            ]);

        // Verify password was changed
        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password_hash));
    }

    public function test_change_password_fails_with_wrong_current_password()
    {
        $user = User::factory()->create([
            'password_hash' => Hash::make('oldpassword'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/change-password', [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Current password is incorrect',
            ]);
    }

    public function test_change_password_validation()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/change-password', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password', 'password']);
    }

    public function test_validate_reset_token_endpoint()
    {
        $user = User::factory()->create(['email' => 'john@example.com']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/auth/validate-reset-token', [
            'email' => 'john@example.com',
            'token' => $token,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Reset token is valid',
            ]);
    }

    public function test_validate_reset_token_fails_with_invalid_token()
    {
        $response = $this->postJson('/api/auth/validate-reset-token', [
            'email' => 'john@example.com',
            'token' => 'invalid-token',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired reset token',
            ]);
    }
}