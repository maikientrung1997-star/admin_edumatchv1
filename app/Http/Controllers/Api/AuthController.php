<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GoogleAuthRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterCenterRequest;
use App\Http\Requests\RegisterStudentRequest;
use App\Http\Requests\RegisterTeacherRequest;
use App\Http\Requests\SendRegistrationOtpRequest;
use App\Models\User;
use App\Services\GoogleAuthService;
use App\Services\RegistrationOtpService;
use App\Support\VietnamesePhone;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function googleChallenge(GoogleAuthService $google): JsonResponse
    {
        return response()->json($google->challenge())->header('Cache-Control', 'no-store');
    }

    public function google(GoogleAuthRequest $request, GoogleAuthService $google): JsonResponse
    {
        $data = $request->validated();
        [$user, $token, $created] = $google->authenticate($data['credential'], $data['nonce'], $data['role'] ?? 'student', $data['password'] ?? null);

        $response = $this->tokenResponse($user, $token, $created ? 'Tạo tài khoản bằng Google thành công.' : 'Đăng nhập bằng Google thành công.');

        return $response->setData($response->getData(true) + ['is_new_user' => $created]);
    }

    public function sendRegistrationOtp(SendRegistrationOtpRequest $request, RegistrationOtpService $otpService): JsonResponse
    {
        $data = $request->validated();
        $otp = $otpService->issue($data['email'], $data['name'] ?? '');
        $response = [
            'message' => 'Mã xác nhận OTP đã được gửi đến email '.$data['email'].'. Vui lòng kiểm tra hộp thư.',
            'email' => $data['email'],
            'expires_in' => config('registration_otp.ttl_minutes') * 60,
            'resend_after' => config('registration_otp.resend_after_seconds'),
        ];
        if (app()->environment(['local', 'testing']) && config('registration_otp.debug')) {
            $response['debug_otp'] = $otp;
        }

        return response()->json($response);
    }

    public function register(Request $request, RegistrationOtpService $otpService): JsonResponse
    {
        $request = app(match ($request->input('role', 'student')) {
            'teacher' => RegisterTeacherRequest::class,
            'center' => RegisterCenterRequest::class,
            default => RegisterStudentRequest::class,
        });
        $data = $request->validated();
        $role = $data['role'] ?? 'student';
        try {
            [$user, $token, $otpError] = DB::transaction(function () use ($data, $role, $otpService) {
                $otpError = $otpService->consume($data['email'], $data['otp']);
                if ($otpError !== null) {
                    return [null, null, $otpError];
                }
                $user = User::create([
                    'role' => $role,
                    'name' => mb_substr($data['name'], 0, 150),
                    'email' => $data['email'] ?? null,
                    'email_verified_at' => now(),
                    'phone' => $data['phone'],
                    'password' => $data['password'],
                    'status' => 'active',
                ]);
                if ($role === 'teacher') {
                    $user->teacherProfile()->create([
                        'full_name' => $user->name, 'date_of_birth' => $data['date_of_birth'],
                        'headline' => $data['headline'] ?? null, 'teaching_field' => $data['teaching_field'] ?? null, 'bio' => $data['bio'] ?? null,
                        'experience_years' => $data['experience_years'] ?? 0,
                        'show_phone' => $data['show_phone'], 'verification_status' => 'unverified',
                    ]);
                } elseif ($role === 'center') {
                    $user->center()->create([
                        'name' => $data['name'],
                        // User ID makes slugs unique even for concurrent registrations and deleted centers.
                        'slug' => mb_substr(Str::slug($data['name']) ?: 'trung-tam', 0, 200).'-'.$user->id,
                        'founded_year' => $data['founded_year'], 'teaching_field' => $data['teaching_field'] ?? null, 'bio' => $data['bio'] ?? null,
                        'hotline' => $data['phone'], 'contact_email' => $data['email'] ?? null,
                        'verification_status' => 'unverified',
                    ]);
                } else {
                    $user->studentProfile()->create([
                        'full_name' => $user->name, 'date_of_birth' => $data['date_of_birth'],
                    ]);
                }
                $user->assignRole(Role::findOrCreate($role, 'web'));

                return [$user, $user->createToken('auth_token')->plainTextToken, null];
            });
        } catch (QueryException $exception) {
            // A concurrent registration can pass validation before the unique insert.
            if (($exception->errorInfo[1] ?? null) === 1062) {
                $message = $exception->errorInfo[2] ?? '';
                if (str_contains($message, 'users_phone_unique')) {
                    throw ValidationException::withMessages(['phone' => 'Số điện thoại này đã được sử dụng.']);
                }
                if (str_contains($message, 'users_email_unique')) {
                    throw ValidationException::withMessages(['email' => 'Email này đã được sử dụng.']);
                }
            }
            throw $exception;
        }

        if ($otpError !== null) {
            throw ValidationException::withMessages(['otp' => $otpError]);
        }

        return $this->tokenResponse($user, $token, 'Đăng ký tài khoản thành công.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $identifier = trim($data['identifier']);
        $query = User::query();
        $user = str_contains($identifier, '@')
            ? $query->where('email', mb_strtolower($identifier))->first()
            : $query->whereIn('phone', VietnamesePhone::variants($identifier))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Thông tin đăng nhập hoặc mật khẩu không chính xác.'], 401);
        }
        if ($user->status === 'blocked') {
            return $this->blockedResponse();
        }

        $token = DB::transaction(function () use ($user) {
            $user->update(['last_login_at' => now()]);

            return $user->createToken('auth_token')->plainTextToken;
        });

        return $this->tokenResponse($user, $token, 'Đăng nhập thành công.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->status === 'blocked') {
            return $this->blockedResponse();
        }

        return response()->json([
            'user' => $user->load(['studentProfile', 'teacherProfile', 'center']),
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return $this->me($request);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Đăng xuất thành công.']);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->status === 'blocked') {
            return $this->blockedResponse();
        }
        $token = DB::transaction(function () use ($user) {
            $currentToken = $user->currentAccessToken();
            if ($currentToken instanceof \Laravel\Sanctum\PersonalAccessToken) {
                $currentToken->delete();
            }

            return $user->createToken('auth_token')->plainTextToken;
        });

        return $this->tokenResponse($user, $token, 'Làm mới phiên đăng nhập thành công.');
    }

    private function tokenResponse(User $user, string $token, string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'user' => $user->load(['studentProfile', 'teacherProfile', 'center']),
            'access_token' => $token,
            'token' => $token,
            'token_type' => 'Bearer',
        ], $status);
    }

    private function blockedResponse(): JsonResponse
    {
        return response()->json(['message' => 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.'], 403);
    }
}
