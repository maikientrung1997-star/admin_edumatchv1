<?php $__env->startSection('title', 'Chi tiết tài khoản - EduMatch'); ?>
<?php $__env->startSection('content'); ?>
<?php
    $role = \App\Support\AccountRoles::of($user);
    $profile = match($role) { 'teacher' => $user->teacherProfile, 'center' => $user->center, 'student' => $user->studentProfile, default => null };
    $verificationLabels = ['unverified'=>'Chưa xác minh', 'pending'=>'Chờ duyệt', 'verified'=>'Đã xác minh', 'rejected'=>'Bị từ chối'];
    $area = $profile ? collect([$profile->ward?->name, $profile->district?->name, $profile->province?->name])->filter()->join(', ') : '';
    $accountFields = [
        'Mã tài khoản' => '#'.$user->id,
        'Họ tên / Tên tài khoản' => $user->name,
        'Vai trò' => $roles[$role] ?? $role,
        'Nhóm quyền' => $user->roles->pluck('name')->join(', '),
        'Email đăng nhập' => $user->email,
        'Số điện thoại đăng nhập' => $user->phone,
        'Trạng thái' => $user->status === 'active' ? 'Hoạt động' : 'Đã khóa',
        'Lý do khóa' => $user->blocked_reason,
        'Thời điểm khóa' => $user->blocked_at?->format('d/m/Y H:i'),
        'Xác thực email' => $user->email_verified_at?->format('d/m/Y H:i') ?? 'Chưa xác thực',
        'Xác thực số điện thoại' => $user->phone_verified_at?->format('d/m/Y H:i') ?? 'Chưa xác thực',
        'Ngày đăng ký' => $user->created_at?->format('d/m/Y H:i'),
        'Cập nhật gần nhất' => $user->updated_at?->format('d/m/Y H:i'),
        'Đăng nhập gần nhất' => $user->last_login_at?->format('d/m/Y H:i'),
    ];
    $profileFields = [];
    if ($profile) {
        $profileFields = $user->role === 'center' ? [
            'Tên trung tâm' => $profile->name, 'Đường dẫn trung tâm' => $profile->slug,
            'Năm thành lập' => $profile->founded_year, 'Hotline' => $profile->hotline,
            'Email liên hệ' => $profile->contact_email, 'Địa chỉ đăng ký' => $profile->registered_address,
        ] : ['Họ tên trong hồ sơ' => $profile->full_name, 'Ngày sinh' => $profile->date_of_birth?->format('d/m/Y')];
        $profileFields += ['Khu vực' => $area, 'Giới thiệu / Mục tiêu học tập' => $profile->bio];
        if ($user->role === 'teacher') {
            $profileFields += ['Tiêu đề giới thiệu' => $profile->headline, 'Số năm kinh nghiệm' => $profile->experience_years,
                'Công khai số điện thoại' => $profile->show_phone ? 'Có' : 'Không'];
        }
        if (in_array($user->role, ['teacher', 'center'])) {
            $profileFields += ['Xác minh hồ sơ' => $verificationLabels[$profile->verification_status] ?? $profile->verification_status,
                'Ngày xác minh' => $profile->verified_at?->format('d/m/Y H:i'),
                'Điểm đánh giá' => $profile->rating_avg, 'Số đánh giá' => $profile->review_count, 'Lượt tin tưởng' => $profile->trust_count];
        }
        $profileFields += ['Ngày tạo hồ sơ' => $profile->created_at?->format('d/m/Y H:i'), 'Cập nhật hồ sơ' => $profile->updated_at?->format('d/m/Y H:i')];
    }
?>
<div class="d-flex justify-content-between align-items-center mb-4"><h1>Chi tiết tài khoản #<?php echo e($user->id); ?></h1><div><a class="btn btn-primary" href="<?php echo e(route('admin.users.edit', $user)); ?>">Chỉnh sửa</a> <a class="btn btn-secondary" href="<?php echo e(route('admin.users.index')); ?>">Danh sách tài khoản</a></div></div>
<div class="row"><div class="col-lg-6"><div class="card p-4">
    <h2 class="h4">Thông tin tài khoản</h2>
    <?php if($user->avatar_url): ?><img class="rounded-circle mb-3" src="<?php echo e($user->avatar_url); ?>" width="90" height="90" style="object-fit:cover" alt="Ảnh đại diện <?php echo e($user->name); ?>"><?php endif; ?>
    <dl class="row mb-0"><?php $__currentLoopData = $accountFields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><dt class="col-sm-5 mb-2"><?php echo e($label); ?></dt><dd class="col-sm-7 mb-2 text-break"><?php echo e($value === null || $value === '' ? 'Chưa cập nhật' : $value); ?></dd><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></dl>
</div></div><div class="col-lg-6"><div class="card p-4"><h2 class="h4">Hồ sơ <?php echo e($roles[$role] ?? $role); ?></h2>
    <?php if($profile): ?>
        <dl class="row mb-0"><?php $__currentLoopData = $profileFields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><dt class="col-sm-5 mb-2"><?php echo e($label); ?></dt><dd class="col-sm-7 mb-2 text-break" style="white-space:pre-line"><?php echo e($value === null || $value === '' ? 'Chưa cập nhật' : $value); ?></dd><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></dl>
        <?php if($user->role === 'center'): ?>
            <?php $__currentLoopData = ['logo_path'=>'Logo trung tâm', 'cover_path'=>'Ảnh bìa trung tâm']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if($profile->{$field}): ?><p class="fw-bold mt-3"><?php echo e($label); ?></p><img src="<?php echo e(preg_match('#^https?://#', $profile->{$field}) ? $profile->{$field} : asset('storage/'.$profile->{$field})); ?>" class="img-fluid rounded" style="max-height:180px" alt="<?php echo e($label); ?>"><?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php endif; ?>
    <?php else: ?><p class="text-muted"><?php echo e(in_array($role, ['admin', 'super_admin']) ? 'Tài khoản quản trị không có hồ sơ học tập / giảng dạy.' : 'Tài khoản chưa cập nhật hồ sơ.'); ?></p><?php endif; ?>
</div></div></div>
<?php if($user->role === 'teacher'): ?>
<div class="card p-4"><h2 class="h4">Môn giảng dạy</h2><ul><?php $__empty_1 = true; $__currentLoopData = $user->subjects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $subject): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><li><?php echo e($subject->name); ?><?php echo e($subject->pivot->level_note ? ' — '.$subject->pivot->level_note : ''); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><li>Chưa cập nhật môn giảng dạy.</li><?php endif; ?></ul>
<h2 class="h4">Bằng cấp / Chứng chỉ</h2><div class="table-responsive"><table class="table"><thead><tr><th>Tên</th><th>Đơn vị cấp</th><th>Ngày cấp</th><th>Hết hạn</th><th>Xác minh</th><th>Công khai</th></tr></thead><tbody><?php $__empty_1 = true; $__currentLoopData = $user->teacherQualifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $qualification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr><td><?php echo e($qualification->title); ?></td><td><?php echo e($qualification->issuer ?: 'Chưa cập nhật'); ?></td><td><?php echo e($qualification->issued_on?->format('d/m/Y') ?: 'Chưa cập nhật'); ?></td><td><?php echo e($qualification->expires_on?->format('d/m/Y') ?: 'Không có'); ?></td><td><?php echo e($qualification->is_verified ? 'Đã xác minh' : 'Chưa xác minh'); ?></td><td><?php echo e($qualification->is_public ? 'Có' : 'Không'); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="6">Chưa cập nhật bằng cấp / chứng chỉ.</td></tr><?php endif; ?></tbody></table></div></div>
<?php endif; ?>
<?php if($user->role === 'center' && $user->center): ?>
<div class="card p-4"><h2 class="h4">Chi nhánh trung tâm</h2><div class="table-responsive"><table class="table"><thead><tr><th>Tên chi nhánh</th><th>Địa chỉ</th><th>Trụ sở chính</th><th>Trạng thái</th></tr></thead><tbody><?php $__empty_1 = true; $__currentLoopData = $user->center->branches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $branch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr><td><?php echo e($branch->name); ?></td><td><?php echo e($branch->address_line); ?></td><td><?php echo e($branch->is_main ? 'Có' : 'Không'); ?></td><td><?php echo e($branch->status === 'active' ? 'Hoạt động' : 'Đã tắt'); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="4">Chưa có chi nhánh.</td></tr><?php endif; ?></tbody></table></div></div>
<?php endif; ?>
<?php if(in_array($user->role, ['teacher', 'center'])): ?>
<div class="card p-4"><h2 class="h4">Lịch sử hồ sơ xác minh</h2><div class="table-responsive"><table class="table"><thead><tr><th>Hồ sơ</th><th>Ngày nộp</th><th>Trạng thái</th><th>Tài liệu</th><th>Người duyệt</th><th>Kết quả</th></tr></thead><tbody>
<?php $__empty_1 = true; $__currentLoopData = $user->submittedVerificationRequests->sortByDesc('id'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $verification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<tr><td><a href="<?php echo e(route('admin.verifications.show', $verification)); ?>">#<?php echo e($verification->id); ?></a></td><td><?php echo e($verification->submitted_at?->format('d/m/Y H:i')); ?></td><td><?php echo e(['pending'=>'Chờ duyệt','approved'=>'Đã duyệt','rejected'=>'Từ chối'][$verification->status] ?? $verification->status); ?></td><td><?php $__currentLoopData = $verification->verificationDocuments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $document): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($document->document_name); ?><?php echo e($document->issuing_authority ? ' — '.$document->issuing_authority : ''); ?><?php echo e($document->issued_year ? ' ('.$document->issued_year.')' : ''); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></td><td><?php echo e($verification->reviewer?->name ?? 'Chưa xử lý'); ?><small class="d-block"><?php echo e($verification->reviewed_at?->format('d/m/Y H:i')); ?></small></td><td><?php echo e($verification->rejection_reason ?: '—'); ?></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="6">Chưa nộp hồ sơ xác minh.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\admin_edumatchv1\resources\views/admin/user/show.blade.php ENDPATH**/ ?>