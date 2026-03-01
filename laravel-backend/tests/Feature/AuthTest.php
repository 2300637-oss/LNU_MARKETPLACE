<?php

namespace Tests\Feature;

use App\Mail\EmailOtpMail;
use App\Models\Role;
use App\Models\StudentIdPrefix;
use App\Models\StudentVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('lnu.allowed_email_domains', ['lnu.edu.ph']);
        config()->set('lnu.student_id_prefix_length', 2);
        config()->set('lnu.password_uncompromised', false);
        config()->set('lnu.email_otp_expires_minutes', 10);

        StudentIdPrefix::query()->create([
            'prefix' => '23',
            'enrollment_year' => 2023,
            'is_active' => true,
            'notes' => 'Test prefix',
        ]);

        Role::query()->create([
            'code' => 'user',
            'name' => 'User',
            'description' => 'Default student account',
            'is_system' => true,
        ]);
    }

    public function test_register_returns_pending_user_and_creates_verification_and_role(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'student_id' => '231234',
            'email' => null,
            'password' => 'Safe!Pass123',
            'password_confirmation' => 'Safe!Pass123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Registration submitted. Await admin approval.')
            ->assertJsonPath('data.user.student_id', '231234')
            ->assertJsonPath('data.user.status', 'pending')
            ->assertJsonStructure(['trace_id']);

        $this->assertDatabaseHas('users', [
            'student_id' => '231234',
            'account_status' => 'pending_verification',
            'email' => null,
        ]);

        $user = User::query()->where('student_id', '231234')->firstOrFail();

        $this->assertDatabaseHas('student_verifications', [
            'user_id' => $user->id,
            'verification_type' => 'email_link',
            'status' => 'pending',
            'sent_to_email' => null,
            'token_hash' => null,
            'otp_hash' => null,
        ]);

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $user->id,
        ]);
    }

    public function test_register_validation_uses_standard_error_format(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'student_id' => '231235',
            'email' => 'jane@not-allowed.com',
            'password' => 'JaneDoe!231235',
            'password_confirmation' => 'JaneDoe!231235',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure([
                'errors' => ['email', 'password'],
                'trace_id',
            ]);
    }

    public function test_login_blocks_pending_accounts(): void
    {
        $user = $this->createUser('231236', 'pending_verification');

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->student_id,
            'password' => 'Safe!Pass123',
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Account not approved yet.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);
    }

    public function test_register_with_email_requires_otp_then_admin_approval_before_login(): void
    {
        Mail::fake();

        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Email User',
            'student_id' => '231240',
            'email' => 'email.user@lnu.edu.ph',
            'password' => 'Safe!Pass123',
            'password_confirmation' => 'Safe!Pass123',
        ]);

        $registerResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.status', 'pending');

        $user = User::query()->where('student_id', '231240')->firstOrFail();
        $this->assertNull($user->email_verified_at);

        $verification = StudentVerification::query()
            ->where('user_id', $user->id)
            ->where('verification_type', 'email_otp')
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        $this->assertNotNull($verification);
        $this->assertNotNull($verification->sent_to_email);
        $this->assertNotNull($verification->otp_hash);
        $this->assertNotNull($verification->expires_at);

        $otp = null;
        Mail::assertSent(EmailOtpMail::class, function (EmailOtpMail $mail) use (&$otp): bool {
            $otp = $mail->otp;

            return true;
        });
        $this->assertNotNull($otp);

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'email.user@lnu.edu.ph',
            'password' => 'Safe!Pass123',
        ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Email not verified yet.');

        $this->postJson('/api/v1/auth/email/otp/verify', [
            'identifier' => 'email.user@lnu.edu.ph',
            'otp' => $otp,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Email verified.');

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'email.user@lnu.edu.ph',
            'password' => 'Safe!Pass123',
        ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Account not approved yet.');

        $user->forceFill(['account_status' => 'active'])->save();

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'email.user@lnu.edu.ph',
            'password' => 'Safe!Pass123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful.');
    }

    public function test_email_otp_resend_endpoint_is_throttled(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Resend User',
            'student_id' => '231241',
            'email' => 'resend.user@lnu.edu.ph',
            'password' => 'Safe!Pass123',
            'password_confirmation' => 'Safe!Pass123',
        ])->assertCreated();

        for ($i = 1; $i <= 3; $i++) {
            $this->postJson('/api/v1/auth/email/otp/resend', [
                'identifier' => 'resend.user@lnu.edu.ph',
            ])
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('message', 'OTP resent.');
        }

        $this->postJson('/api/v1/auth/email/otp/resend', [
            'identifier' => 'resend.user@lnu.edu.ph',
        ])
            ->assertStatus(429);
    }

    public function test_login_returns_token_for_approved_account(): void
    {
        $user = $this->createUser('231237', 'active', 'student@lnu.edu.ph');

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'student@lnu.edu.ph',
            'password' => 'Safe!Pass123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.status', 'approved')
            ->assertJsonPath('data.user.roles.0', 'user')
            ->assertJsonStructure([
                'data' => ['token', 'user' => ['id', 'name', 'student_id', 'email', 'status', 'roles']],
                'trace_id',
            ]);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = $this->createUser('231238', 'active');
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Logged out.')
            ->assertJsonPath('data', null);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_me_requires_auth_and_returns_user_profile(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);

        $user = $this->createUser('231239', 'active');
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.student_id', '231239')
            ->assertJsonPath('data.user.status', 'approved')
            ->assertJsonPath('data.user.roles.0', 'user')
            ->assertJsonStructure(['trace_id']);
    }

    public function test_unhandled_exception_returns_standard_500_envelope(): void
    {
        Route::middleware('api')->get('/api/v1/test/boom', function () {
            throw new \RuntimeException('boom');
        });

        $this->getJson('/api/v1/test/boom')
            ->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Server error.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);
    }

    private function createUser(string $studentId, string $status, ?string $email = null, bool $emailVerified = true): User
    {
        $user = User::query()->create([
            'student_id' => $studentId,
            'student_id_prefix' => '23',
            'email' => $email,
            'password' => 'Safe!Pass123',
            'first_name' => 'Sample',
            'last_name' => 'Student',
            'middle_name' => null,
            'account_status' => $status,
            'email_verified_at' => $email !== null && $emailVerified ? now() : null,
        ]);

        $role = Role::query()->where('code', 'user')->firstOrFail();
        $user->roles()->syncWithoutDetaching([
            $role->id => [
                'assigned_by_user_id' => null,
                'assigned_at' => now(),
            ],
        ]);

        return $user;
    }
}
