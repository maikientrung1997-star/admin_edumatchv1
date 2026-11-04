<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['teacher', 'center'], true);
    }

    public function rules(): array
    {
        $isCenter = $this->user()->role === 'center';
        $types = config('verification.'.$this->user()->role.'_types', []);

        return [
            'documents' => ['required', 'array', 'min:'.($isCenter ? 1 : 3)],
            'documents.*' => ['array:document_type,document_name,issuing_authority,issued_year,file'],
            'documents.*.document_type' => ['required', Rule::in($isCenter ? ['business_registration'] : array_keys($types))],
            'documents.*.document_name' => [$isCenter ? 'nullable' : 'required', 'string', 'max:200'],
            'documents.*.issuing_authority' => [$isCenter ? 'nullable' : 'required', 'string', 'max:200'],
            'documents.*.issued_year' => [$isCenter ? 'nullable' : 'required', 'integer', 'min:1900', 'max:'.now()->year],
            'documents.*.file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf'],
            'center_details' => [$isCenter ? 'required' : 'nullable', 'array:center_name,training_field,training_address,company_name,company_address,tax_code,representative_name,representative_phone'],
            'center_details.center_name' => [$isCenter ? 'required' : 'nullable', 'string', 'max:160'],
            'center_details.training_field' => [$isCenter ? 'required' : 'nullable', 'string', 'max:200'],
            'center_details.training_address' => [$isCenter ? 'required' : 'nullable', 'string', 'max:300'],
            'center_details.company_name' => [$isCenter ? 'required' : 'nullable', 'string', 'max:160'],
            'center_details.company_address' => [$isCenter ? 'required' : 'nullable', 'string', 'max:300'],
            'center_details.tax_code' => [$isCenter ? 'required' : 'nullable', 'regex:/^\d{10}(?:\d{3})?$/'],
            'center_details.representative_name' => [$isCenter ? 'required' : 'nullable', 'string', 'max:120'],
            'center_details.representative_phone' => [$isCenter ? 'required' : 'nullable', 'regex:/^(?:0|\+84)\d{9,10}$/'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $documents = $this->input('documents');
            if (! is_array($documents)) {
                return;
            }
            $types = array_column(array_filter($documents, 'is_array'), 'document_type');
            $required = $this->user()->role === 'center'
                ? ['business_registration']
                : ['identity_front', 'identity_back'];
            foreach ($required as $type) {
                if (! in_array($type, $types, true)) {
                    $validator->errors()->add('documents', 'Vui lòng nộp đầy đủ tài liệu bắt buộc và hai mặt CCCD.');
                }
            }
            if ($this->user()->role === 'teacher' && ! array_diff($types, ['identity_front', 'identity_back'])) {
                $validator->errors()->add('documents', 'Vui lòng nộp ít nhất một bằng cấp hoặc chứng chỉ chuyên môn.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'documents.required' => 'Vui lòng đính kèm hồ sơ xác minh.',
            'documents.*.file.mimes' => 'Chỉ chấp nhận ảnh JPG/PNG hoặc PDF.',
            'documents.*.issued_year.max' => 'Năm cấp không được nằm trong tương lai.',
        ];
    }
}
