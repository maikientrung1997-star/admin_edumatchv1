<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class GoogleAuthService
{
    private const CHALLENGE_TTL = 600;

    private function clientId(): string
    {
        $id = trim((string) config('services.google.client_id'));
        abort_if($id === '', 503, 'Đăng nhập Google hiện chưa sẵn sàng. Vui lòng đăng nhập bằng email hoặc số điện thoại.');

        return $id;
    }

    public function challenge(): array
    {
        $this->clientId();
        $nonce = bin2hex(random_bytes(32));
        Cache::put($this->nonceKey($nonce), true, self::CHALLENGE_TTL);

        return ['nonce' => $nonce, 'expires_in' => self::CHALLENGE_TTL];
    }

    private function nonceKey(string $nonce): string
    {
        return 'google-auth:nonce:'.hash('sha256', $nonce);
    }

    public function verifyIdToken(string $credential, string $nonce): array
    {
        $clientId = $this->clientId();
        abort_unless(Cache::has($this->nonceKey($nonce)), 401, 'Phiên xác thực Google đã hết hạn hoặc đã được sử dụng. Vui lòng thử lại.');
        try {
            $response = Http::acceptJson()->connectTimeout(3)->timeout(5)
                ->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $credential]);
        } catch (ConnectionException) {
            abort(503, 'Chưa thể kết nối Google để xác thực. Vui lòng thử lại sau.');
        }
        abort_if($response->serverError() || $response->status() === 429, 503, 'Google đang bận. Vui lòng thử lại sau.');
        abort_unless($response->successful(), 401, 'Thông tin xác thực Google không hợp lệ hoặc đã hết hạn.');
        $claims = $response->json();
        // tokeninfo verifies the entire signed JWT. Read nonce from that verified
        // JWT because tokeninfo does not guarantee that every claim is echoed.
        $parts = explode('.', $credential);
        $payload = isset($parts[1]) ? base64_decode(strtr($parts[1], '-_', '+/'), true) : false;
        $payload = $payload === false ? null : json_decode($payload, true);
        $valid = is_array($claims) && is_array($payload)
            && ($claims['aud'] ?? null) === $clientId
            && (! isset($claims['azp']) || $claims['azp'] === $clientId)
            && in_array($claims['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)
            && is_numeric($claims['exp'] ?? null) && (int) $claims['exp'] > now()->timestamp
            && in_array($claims['email_verified'] ?? null, [true, 'true', 1, '1'], true)
            && is_string($claims['sub'] ?? null) && preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $claims['sub'])
            && is_string($claims['email'] ?? null) && strlen($claims['email']) <= 191
            && filter_var($claims['email'], FILTER_VALIDATE_EMAIL)
            && is_string($payload['nonce'] ?? null) && hash_equals($nonce, $payload['nonce']);
        abort_unless($valid, 401, 'Không thể xác minh thông tin tài khoản Google. Vui lòng chọn lại tài khoản.');
        $claims['email'] = mb_strtolower(trim($claims['email']));

        return $claims;
    }

    public function authenticate(string $credential, string $nonce, string $preferredRole = 'student', ?string $password = null): array
    {
        $claims = $this->verifyIdToken($credential, $nonce);
        // Keep the nonce until account/profile/token commit succeeds. This also
        // allows password confirmation for linking an existing non-Google email.
        $lock = Cache::lock($this->nonceKey($nonce).':lock', 15);
        abort_unless($lock->get(), 409, 'Yêu cầu xác thực này đang được xử lý.');
        try {
            abort_unless(Cache::has($this->nonceKey($nonce)), 401, 'Phiên xác thực Google đã được sử dụng. Vui lòng thử lại.');
            for ($attempt = 0; $attempt < 2; $attempt++) {
                try {
                    $result = DB::transaction(fn () => $this->createSession($claims, $preferredRole, $password), 3);
                    Cache::forget($this->nonceKey($nonce));

                    return $result;
                } catch (QueryException $exception) {
                    // A concurrent first login may win either unique index.
                    if (($exception->errorInfo[1] ?? null) !== 1062 || $attempt !== 0) {
                        throw $exception;
                    }
                }
            }
        } finally {
            $lock->release();
        }

        abort(409, 'Tài khoản vừa được cập nhật. Vui lòng thử đăng nhập lại.');
    }

    private function createSession(array $claims, string $preferredRole, ?string $password): array
    {
        $user = User::withTrashed()->where('google_id', $claims['sub'])->lockForUpdate()->first();
        if (! $user) {
            $user = User::where('email', $claims['email'])->lockForUpdate()->first();
        }
        $created = ! $user;
        if ($user) {
            abort_if($user->trashed(), 403, 'Tài khoản này đã bị xóa. Vui lòng liên hệ quản trị viên.');
            abort_unless($user->status === 'active', 403, 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.');
            abort_if($user->google_id && $user->google_id !== $claims['sub'], 409, 'Email này đã liên kết với một tài khoản Google khác.');
            $authoritative = str_ends_with($claims['email'], '@gmail.com') || ! empty($claims['hd']);
            $privileged = in_array($user->role, ['admin', 'super_admin'], true) || $user->hasAnyRole(['admin', 'super_admin']) || $user->can('access_admin');
            if (! $user->google_id && (! $authoritative || $privileged)
                && (! $password || ! Hash::check($password, $user->password))) {
                throw new HttpResponseException(response()->json([
                    'message' => $password ? 'Mật khẩu EduMatch chưa chính xác.' : 'Vui lòng xác nhận mật khẩu EduMatch để liên kết tài khoản hiện có.',
                    'code' => 'google_link_password_required', 'requires_password' => true,
                ], 409));
            }
            $user->google_id = $claims['sub'];
            if ($user->email === $claims['email'] && ! $user->email_verified_at) {
                $user->email_verified_at = now();
            }
        } else {
            abort_unless(in_array($preferredRole, ['student', 'teacher', 'center'], true), 422);
            $name = trim(is_string($claims['name'] ?? null) ? $claims['name'] : '') ?: Str::before($claims['email'], '@');
            $user = User::create([
                'name' => mb_substr($name, 0, 150), 'email' => $claims['email'], 'google_id' => $claims['sub'],
                'email_verified_at' => now(), 'password' => Str::random(64), 'role' => $preferredRole, 'status' => 'active',
            ]);
            if ($preferredRole === 'teacher') {
                $user->teacherProfile()->create(['full_name' => $user->name, 'verification_status' => 'unverified', 'show_phone' => false]);
            } elseif ($preferredRole === 'center') {
                $user->center()->create(['name' => $user->name, 'slug' => (Str::slug($user->name) ?: 'trung-tam').'-'.$user->id,
                    'contact_email' => $user->email, 'verification_status' => 'unverified']);
            } else {
                $user->studentProfile()->create(['full_name' => $user->name]);
            }
            $user->assignRole(Role::findOrCreate($preferredRole, 'web'));
        }
        $picture = $claims['picture'] ?? null;
        if (! $user->avatar_path && is_string($picture) && strlen($picture) <= 255
            && filter_var($picture, FILTER_VALIDATE_URL) && parse_url($picture, PHP_URL_SCHEME) === 'https') {
            $user->avatar_path = $picture;
        }
        $user->last_login_at = now();
        $user->save();

        return [$user, $user->createToken('auth_token')->plainTextToken, $created];
    }
}
