<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GoogleAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'credential' => ['bail', 'required', 'string', 'max:8192', 'regex:/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/D'],
            'nonce' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'role' => ['nullable', 'string', Rule::in(['student', 'teacher', 'center'])],
            'password' => ['nullable', 'string', 'max:128'],
        ];
    }

    public function messages(): array
    {
        return ['credential.required' => 'Vui lòng xác thực tài khoản Google.',
            'credential.regex' => 'Thông tin xác thực Google không hợp lệ.',
            'role.in' => 'Vai trò đăng ký không hợp lệ.',
            'nonce.required' => 'Phiên xác thực Google đã hết hạn. Vui lòng thử lại.'];
    }
}
