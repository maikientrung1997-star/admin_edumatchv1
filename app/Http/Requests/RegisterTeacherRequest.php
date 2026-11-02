<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class RegisterTeacherRequest extends RegisterStudentRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $rawExperienceText = $this->input('experience');
        $experience = $this->input('experience_years', $rawExperienceText ?? 0);
        if (is_string($experience) && ! is_numeric($experience)) {
            $experience = preg_match('/\d+/', $experience, $matches) ? (int) $matches[0] : 0;
        }
        $experienceYears = is_numeric($experience) ? max(0, min(60, (int) $experience)) : 0;
        $this->merge([
            'headline' => $this->input('headline') ?? $this->input('qualification') ?? $this->input('qualifications'),
            'bio' => $this->input('bio') ?? $this->input('biography'),
            'teaching_field' => $this->input('teaching_field') ?? $this->input('teachingField'),
            'experience' => is_string($rawExperienceText) ? trim($rawExperienceText) : null,
            'experience_years' => $experienceYears,
            'show_phone' => $this->input('show_phone', $this->input('isPhonePublic', false)),
        ]);
    }

    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'role' => ['required', Rule::in(['teacher'])],
            'headline' => ['nullable', 'string'],
            // Teacher capability profiles, qualifications, and teaching experience are
            // long-form content and intentionally have no character limit.
            'bio' => ['nullable', 'string'],
            'experience' => ['nullable', 'string'],
            'teaching_field' => ['nullable', 'string', 'max:40'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:60'],
            'show_phone' => ['required', 'boolean'],
        ]);
    }

    public function messages(): array
    {
        return array_replace(parent::messages(), [
            'headline.max' => 'Bằng cấp/tiêu đề nghề nghiệp tối đa 200 ký tự.',
            'teaching_field.max' => 'Lĩnh vực giảng dạy tối đa 40 ký tự.',
            'experience_years.integer' => 'Vui lòng nhập số năm kinh nghiệm hợp lệ.',
            'experience_years.max' => 'Số năm kinh nghiệm tối đa 60 năm.',
            'role.in' => 'Vai trò đăng ký không hợp lệ.',
        ]);
    }
}
