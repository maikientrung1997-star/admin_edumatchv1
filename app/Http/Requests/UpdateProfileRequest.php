<?php

namespace App\Http\Requests;

use App\Models\AdministrativeArea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['student', 'teacher', 'center'], true);
    }

    public function rules(): array
    {
        $role = $this->user()->role;
        $rules = [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:'.($role === 'center' ? '200' : '150')],
            // Capability profiles for teachers and centers are intentionally
            // long-form fields. Do not add a max length here.
            'bio' => $role === 'student'
                ? ['sometimes', 'nullable', 'string', 'max:1000']
                : ['sometimes', 'nullable', 'string'],
        ];

        if ($role === 'student') {
            $rules['date_of_birth'] = ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'];
            foreach (['province', 'district', 'ward'] as $level) {
                $rules[$level.'_id'] = ['sometimes', 'nullable', 'integer', Rule::exists('administrative_areas', 'id')->where('level', $level)->where('is_active', true)];
            }
        } elseif ($role === 'teacher') {
            $rules['date_of_birth'] = ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'];
            $rules['headline'] = ['sometimes', 'nullable', 'string'];
            $rules['experience'] = ['sometimes', 'nullable', 'string'];
            $rules['experience_years'] = ['sometimes', 'nullable', 'integer', 'min:0', 'max:60'];
            $rules['teaching_field'] = ['sometimes', 'nullable', 'string', 'max:40'];
        } elseif ($role === 'center') {
            $rules['founded_year'] = ['sometimes', 'nullable', 'integer', 'min:1950', 'max:'.now()->year];
            $rules['teaching_field'] = ['sometimes', 'nullable', 'string', 'max:40'];
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        if ($this->user()->role !== 'student') {
            return;
        }

        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $profile = $this->user()->studentProfile;
            $province = $this->input('province_id', $profile?->province_id);
            $district = $this->input('district_id', $profile?->district_id);
            $ward = $this->input('ward_id', $profile?->ward_id);
            if ($district && (! $province || (int) AdministrativeArea::find($district)?->parent_id !== (int) $province)) {
                $validator->errors()->add('district_id', 'Quận/huyện phải thuộc tỉnh/thành đã chọn.');
            }
            if ($ward && (! $province || (int) AdministrativeArea::find($ward)?->parent_id !== (int) ($district ?: $province))) {
                $validator->errors()->add('ward_id', 'Phường/xã phải thuộc khu vực đã chọn.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.max' => $this->user()->role === 'center' ? 'Tên trung tâm tối đa 200 ký tự.' : 'Họ và tên tối đa 150 ký tự.',
            'bio.max' => 'Giới thiệu/mục tiêu học tập tối đa 1000 ký tự.',
            'date_of_birth.before_or_equal' => 'Ngày sinh không được nằm trong tương lai.',
            'teaching_field.max' => 'Lĩnh vực giảng dạy tối đa 40 ký tự.',
            '*.exists' => 'Khu vực được chọn không hợp lệ hoặc không còn hoạt động.',
        ];
    }
}
