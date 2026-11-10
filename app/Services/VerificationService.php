<?php

namespace App\Services;

use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VerificationService
{
    public function submit(User $user, array $documents, ?array $centerDetails = null): VerificationRequest
    {
        $paths = [];
        try {
            return DB::transaction(function () use ($user, $documents, $centerDetails, &$paths) {
                $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                abort_unless(in_array($user->role, ['teacher', 'center'], true) && $user->status === 'active', 403);
                $profile = $user->role === 'teacher' ? $user->teacherProfile()->first() : $user->center()->first();
                abort_unless($profile, 422, 'Vui lòng hoàn thiện hồ sơ trước khi nộp xác minh.');
                $history = VerificationRequest::where('submitted_by_user_id', $user->id);
                if ($history->clone()->where('status', 'pending')->exists() || $profile->verification_status === 'verified') {
                    throw ValidationException::withMessages(['documents' => 'Hồ sơ đang chờ duyệt hoặc đã được xác minh. Không thể nộp thêm.']);
                }
                $record = VerificationRequest::create([
                    'teacher_user_id' => $user->role === 'teacher' ? $user->id : null,
                    'center_id' => $user->role === 'center' ? $profile->id : null,
                    'submitted_by_user_id' => $user->id, 'status' => 'pending', 'submitted_at' => now(),
                    'center_verification_details' => $user->role === 'center' ? $centerDetails : null,
                ]);
                foreach ($documents as $document) {
                    $file = $document['file'];
                    $path = $file->store((string) $record->id, 'verification');
                    $paths[] = $path;
                    $record->verificationDocuments()->create([
                        'document_type' => $document['document_type'], 'document_name' => $document['document_name'],
                        'issuing_authority' => $document['issuing_authority'], 'issued_year' => $document['issued_year'],
                        'file_path' => $path, 'original_name' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255),
                        'mime_type' => $file->getMimeType(), 'status' => 'pending',
                    ]);
                }
                $profile->update(['verification_status' => 'pending', 'verified_at' => null]);

                return $record->load('verificationDocuments');
            });
        } catch (\Throwable $exception) {
            foreach ($paths as $path) {
                Storage::disk('verification')->delete($path);
            }
            throw $exception;
        }
    }

    public function review(VerificationRequest $request, User $admin, bool $approved, ?string $reason = null): void
    {
        DB::transaction(function () use ($request, $admin, $approved, $reason) {
            $owner = User::whereKey($request->submitted_by_user_id)->lockForUpdate()->firstOrFail();
            $record = VerificationRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'pending', 409, 'Hồ sơ đã được xử lý. Vui lòng tải lại trang.');
            abort_if((int) $admin->id === (int) $owner->id, 403, 'Không thể tự phê duyệt hồ sơ của mình.');
            $latest = VerificationRequest::where('submitted_by_user_id', $owner->id)->max('id');
            abort_unless((int) $latest === (int) $record->id, 409, 'Chỉ có thể xét duyệt hồ sơ mới nhất.');
            $profile = $record->teacher_user_id ? $owner->teacherProfile()->first() : $record->center;
            abort_unless($profile, 409, 'Hồ sơ chủ thể không còn tồn tại.');
            $status = $approved ? 'approved' : 'rejected';
            $record->update(['status' => $status, 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'rejection_reason' => $approved ? null : $reason]);
            $profile->update(['verification_status' => $approved ? 'verified' : 'rejected', 'verified_at' => $approved ? now() : null]);
            $record->verificationDocuments()->update(['status' => $status, 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'admin_note' => $approved ? null : $reason]);
            $isCenter = (bool) $record->center_id || $owner->role === 'center';
            $cleanReason = trim((string) $reason);
            $rejectedTitle = $isCenter ? 'Hồ sơ xác thực chưa được xét duyệt' : 'Hồ sơ xác minh cần bổ sung';
            $rejectedContent = $isCenter
                ? 'Hồ sơ chưa được xét duyệt. Lý do: '.$cleanReason.'. Đội ngũ EduMatch sẽ liên hệ trực tiếp qua SĐT để hướng dẫn.'
                : $reason;
            app(RealtimeNotificationService::class)->send(
                $owner->id,
                'verification_result',
                $approved ? 'Hồ sơ xác minh đã được phê duyệt' : $rejectedTitle,
                $approved ? 'Hồ sơ của bạn đã được phê duyệt và cấp huy hiệu xác minh.' : $rejectedContent,
                ['request_id' => $record->id, 'status' => $status, 'rejection_reason' => $approved ? null : $cleanReason, 'link' => '/xac-minh'],
            );
        });
    }
}
