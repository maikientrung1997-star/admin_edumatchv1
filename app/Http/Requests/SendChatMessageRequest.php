<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active';
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('metadata'))) {
            $decoded = json_decode($this->input('metadata'), true);
            if (is_array($decoded)) {
                $this->merge(['metadata' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return ['client_id' => ['required', 'uuid'], 'body' => ['nullable', 'string', 'max:4000'],
            'metadata' => ['nullable', 'array:math,formula,reply_to_id'], 'metadata.math' => ['sometimes', 'boolean'],
            'metadata.formula' => ['nullable', 'string', 'max:4000'], 'metadata.reply_to_id' => ['nullable', 'integer', 'min:1'],
            'files' => ['nullable', 'array', 'max:3'],
            'files.*' => ['required', 'file', 'max:10240', 'extensions:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,zip,txt', 'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,zip,txt']];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if (! trim((string) $this->input('body')) && ! $this->hasFile('files') && ! trim((string) $this->input('metadata.formula'))) {
                $validator->errors()->add('body', 'Vui lòng nhập tin nhắn, công thức hoặc chọn tệp.');
            }
            foreach ($this->file('files', []) as $file) {
                if ($file->isValid() && str_starts_with($file->getMimeType() ?? '', 'image/')) {
                    $size = @getimagesize($file->getPathname());
                    if (! $size || $size[0] > 12000 || $size[1] > 12000) {
                        $validator->errors()->add('files', 'Ảnh không hợp lệ hoặc kích thước ảnh quá lớn.');
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return ['files.max' => 'Mỗi tin nhắn tối đa 3 tệp.', 'files.*.max' => 'Mỗi tệp tối đa 10 MB.',
            'files.*.mimes' => 'Loại tệp không được hỗ trợ.', 'files.*.extensions' => 'Phần mở rộng tệp không được hỗ trợ.',
            'body.max' => 'Tin nhắn tối đa 4.000 ký tự.'];
    }
}
