<?php

namespace Tests\Feature;

use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\IsolatedMysqlTestCase;

class GoogleAuthTest extends IsolatedMysqlTestCase
{
    private const CLIENT = 'edumatch-test.apps.googleusercontent.com';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => self::CLIENT]);
        Http::preventStrayRequests();
    }

    private function claims(array $overrides = []): array
    {
        return $overrides + ['sub' => 'google-stable-subject', 'aud' => self::CLIENT, 'iss' => 'https://accounts.google.com',
            'exp' => (string) now()->addHour()->timestamp, 'email_verified' => 'true', 'email' => 'google.edumatch@gmail.com',
            'name' => 'Nguyễn Google', 'picture' => 'https://lh3.googleusercontent.com/avatar.jpg'];
    }

    private function payload(array $claims = [], string $role = 'student'): array
    {
        $nonce = $this->getJson('/api/auth/google/challenge')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json('nonce');
        $claims = $this->claims($claims);
        $base64 = fn ($value) => rtrim(strtr(base64_encode(json_encode($value)), '+/', '-_'), '=');
        $credential = $base64(['alg' => 'RS256']).'.'.$base64($claims + ['nonce' => $nonce]).'.verified-by-google';
        $this->googleResponds(Http::response($claims));

        return compact('nonce', 'credential', 'role');
    }

    private function googleResponds($response): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['oauth2.googleapis.com/tokeninfo*' => $response]);
    }

    private function account(array $values = []): User
    {
        return User::create($values + ['name' => 'Tài khoản có sẵn', 'email' => 'google.edumatch@gmail.com', 'password' => 'Password@123', 'role' => 'teacher']);
    }

    public function test_google_creates_each_business_role_with_correct_unverified_profile_and_real_sanctum_session(): void
    {
        foreach (['student', 'teacher', 'center'] as $role) {
            $payload = $this->payload(['sub' => 'subject-'.$role, 'email' => $role.'@gmail.com'], $role);
            $response = $this->postJson('/api/auth/google', $payload)->assertOk()->assertJsonPath('is_new_user', true)
                ->assertJsonPath('user.role', $role)->assertJsonMissingPath('user.password')->assertJsonMissingPath('user.google_id');
            $user = User::findOrFail($response->json('user.id'));
            $this->assertTrue($user->hasRole($role));
            $this->assertNotNull($user->email_verified_at);
            $this->assertNotNull($user->last_login_at);
            $this->assertNull($user->phone);
            $this->assertSame('https://lh3.googleusercontent.com/avatar.jpg', $user->avatar_path);
            $this->assertNotEmpty($user->password);
            $profile = match ($role) {
                'teacher' => $user->teacherProfile, 'center' => $user->center, default => $user->studentProfile
            };
            $this->assertNotNull($profile);
            if ($role !== 'student') {
                $this->assertSame('unverified', $profile->verification_status);
                $this->assertNull($profile->verified_at);
            }
            $this->app['auth']->forgetGuards();
            $this->withToken($response->json('token'))->getJson('/api/auth/me')->assertOk()->assertJsonPath('user.id', $user->id);
        }
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('auth_verification_tokens', 0);
    }

    public function test_matching_email_links_existing_user_without_changing_role_password_profile_or_uploaded_avatar(): void
    {
        $user = $this->account(['phone' => '+84981234567', 'avatar_path' => 'avatars/uploaded.jpg']);
        $user->assignRole(Role::findOrCreate('teacher', 'web'));
        $profile = $user->teacherProfile()->create(['full_name' => $user->name, 'headline' => 'Giữ nguyên bằng cấp', 'verification_status' => 'verified', 'verified_at' => now()]);
        $oldPassword = $user->password;
        $this->postJson('/api/auth/google', $this->payload(['email' => 'GOOGLE.EDUMATCH@GMAIL.COM'], 'center'))->assertOk()
            ->assertJsonPath('user.id', $user->id)->assertJsonPath('user.role', 'teacher')->assertJsonPath('is_new_user', false);
        $user->refresh();
        $this->assertSame('google-stable-subject', $user->google_id);
        $this->assertSame($oldPassword, $user->password);
        $this->assertSame('avatars/uploaded.jpg', $user->avatar_path);
        $this->assertSame($profile->id, $user->teacherProfile->id);
        $this->assertSame('verified', $user->teacherProfile->verification_status);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('centers', 0);
    }

    public function test_stable_google_subject_is_used_even_if_google_email_changes(): void
    {
        $user = $this->account(['google_id' => 'google-stable-subject', 'email' => 'original@gmail.com']);
        $this->postJson('/api/auth/google', $this->payload())->assertOk()->assertJsonPath('user.id', $user->id);
        $this->assertSame('original@gmail.com', $user->fresh()->email);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_invalid_claims_nonce_and_admin_role_cannot_create_sessions(): void
    {
        foreach ([['aud' => 'another-app'], ['iss' => 'https://evil.example'], ['exp' => now()->subMinute()->timestamp],
            ['email_verified' => 'false'], ['sub' => str_repeat('x', 101)], ['email' => 'invalid'], ['azp' => 'other-app'], ['nonce' => 'wrong-nonce']] as $claims) {
            $this->postJson('/api/auth/google', $this->payload($claims))->assertUnauthorized();
        }
        $this->postJson('/api/auth/google', $this->payload([], 'super_admin'))->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->postJson('/api/auth/google', ['credential' => 'forged', 'nonce' => 'bad'])->assertUnprocessable();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_expired_or_used_challenge_cannot_be_replayed(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/auth/google', $payload)->assertOk();
        $this->postJson('/api/auth/google', $payload)->assertUnauthorized();
        $payload = $this->payload();
        $this->travel(11)->minutes();
        $this->postJson('/api/auth/google', $payload)->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_blocked_deleted_and_conflicting_google_accounts_cannot_sign_in(): void
    {
        $user = $this->account(['status' => 'blocked']);
        $this->postJson('/api/auth/google', $this->payload())->assertForbidden();
        $this->assertNull($user->fresh()->google_id);
        $user->update(['status' => 'active', 'google_id' => 'different-google-subject']);
        $this->postJson('/api/auth/google', $this->payload())->assertConflict();
        $user->update(['google_id' => 'google-stable-subject']);
        $user->delete();
        $this->postJson('/api/auth/google', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_non_authoritative_email_and_admin_linking_require_existing_password(): void
    {
        foreach (['person@example.com', 'google.edumatch@gmail.com'] as $index => $email) {
            $user = $this->account(['email' => $email, 'role' => $index ? 'super_admin' : 'student']);
            $payload = $this->payload(['sub' => 'link-'.$index, 'email' => $email]);
            $this->postJson('/api/auth/google', $payload)->assertConflict()->assertJsonPath('requires_password', true);
            $this->postJson('/api/auth/google', $payload + ['password' => 'wrong-password'])->assertConflict();
            $this->assertNull($user->fresh()->google_id);
            $this->postJson('/api/auth/google', $payload + ['password' => 'Password@123'])->assertOk()->assertJsonPath('user.id', $user->id);
        }
    }

    public function test_workspace_verified_email_can_link_without_password(): void
    {
        $user = $this->account(['email' => 'teacher@workspace.example']);
        $this->postJson('/api/auth/google', $this->payload(['email' => $user->email, 'hd' => 'workspace.example']))->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_missing_configuration_fails_closed_and_google_errors_do_not_leak_credentials(): void
    {
        $payload = $this->payload();
        config(['services.google.client_id' => null]);
        $this->getJson('/api/auth/google/challenge')->assertServiceUnavailable();
        $this->postJson('/api/auth/google', $payload)->assertServiceUnavailable();
        Http::assertNothingSent();
        config(['services.google.client_id' => self::CLIENT]);
        $this->googleResponds(Http::response(['error' => $payload['credential']], 400));
        $this->postJson('/api/auth/google', $payload)->assertUnauthorized()->assertDontSee($payload['credential']);
        $this->googleResponds(Http::response([], 503));
        $this->postJson('/api/auth/google', $payload)->assertServiceUnavailable();
        $this->googleResponds(fn () => throw new ConnectionException('private transport details'));
        $this->postJson('/api/auth/google', $payload)->assertServiceUnavailable()->assertDontSee('private transport details');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_account_can_be_registered_again_after_admin_deletion(): void
    {
        $response = $this->postJson('/api/auth/google', $this->payload())->assertOk();
        $old = User::findOrFail($response->json('user.id'));
        app(AccountDeletionService::class)->delete($old);
        $this->assertNull($old->fresh()->google_id);
        $this->postJson('/api/auth/google', $this->payload())->assertOk()->assertJsonPath('is_new_user', true)
            ->assertJsonPath('user.id', fn ($id) => $id !== $old->id);
    }

    public function test_profile_failure_rolls_back_user_link_and_token_and_allows_retry(): void
    {
        $payload = $this->payload([], 'teacher');
        TeacherProfile::creating(fn () => throw new \RuntimeException('Fixture failure'));
        try {
            $this->postJson('/api/auth/google', $payload)->assertStatus(500);
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('personal_access_tokens', 0);
            $this->assertTrue(Cache::has('google-auth:nonce:'.hash('sha256', $payload['nonce'])));
        } finally {
            TeacherProfile::flushEventListeners();
        }
        $this->postJson('/api/auth/google', $payload)->assertOk();
    }
}
