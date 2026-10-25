<?php $__env->startSection('title', 'Quản lý tài khoản - EduMatch'); ?>
<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1>Quản lý tài khoản</h1><p class="text-muted mb-0">Thông tin tài khoản, vai trò và hồ sơ EduMatch.</p></div>
    <a href="<?php echo e(route('admin.users.create')); ?>" class="btn btn-primary">Thêm tài khoản</a>
</div>
<form method="GET" class="row g-2 mb-3">
    <div class="col-md-5"><label for="q" class="form-label">Tìm kiếm</label><input id="q" name="q" class="form-control" value="<?php echo e(request('q')); ?>" placeholder="Tên, email, số điện thoại hoặc ID"></div>
    <div class="col-md-3"><label for="role-filter" class="form-label">Vai trò</label><select id="role-filter" name="role" class="form-select"><option value="">Tất cả vai trò</option><?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($value); ?>" <?php if(request('role') === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
    <div class="col-md-2"><label for="status-filter" class="form-label">Trạng thái</label><select id="status-filter" name="status" class="form-select"><option value="">Tất cả</option><option value="active" <?php if(request('status') === 'active'): echo 'selected'; endif; ?>>Hoạt động</option><option value="blocked" <?php if(request('status') === 'blocked'): echo 'selected'; endif; ?>>Đã khóa</option></select></div>
    <div class="col-md-2 d-flex align-items-end gap-2"><button class="btn btn-primary">Lọc</button><a class="btn btn-outline-secondary" href="<?php echo e(route('admin.users.index')); ?>">Đặt lại</a></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-striped align-middle">
    <thead><tr><th>ID</th><th>Tài khoản</th><th>Liên hệ</th><th>Vai trò</th><th>Trạng thái</th><th>Xác minh</th><th>Ngày đăng ký</th><th>Thao tác</th></tr></thead>
    <tbody>
    <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <?php
            $role = \App\Support\AccountRoles::of($user);
            $profile = $role === 'teacher' ? $user->teacherProfile : ($role === 'center' ? $user->center : $user->studentProfile);
        ?>
        <tr>
            <td><?php echo e($user->id); ?></td>
            <td><div class="d-flex align-items-center gap-2">
                <?php if($user->avatar_url): ?><img src="<?php echo e($user->avatar_url); ?>" width="44" height="44" style="object-fit:cover;border-radius:50%" alt="Ảnh <?php echo e($user->name); ?>">
                <?php else: ?><span class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center" style="width:44px;height:44px" aria-hidden="true"><i class="fas fa-user text-muted"></i></span><?php endif; ?>
                <div><a href="<?php echo e(route('admin.users.show', $user)); ?>" class="fw-bold"><?php echo e($user->name ?: ($profile?->full_name ?? $profile?->name ?? 'Chưa cập nhật')); ?></a>
                    <?php if($role === 'center' && $profile?->name !== $user->name): ?><small class="d-block text-muted"><?php echo e($profile?->name); ?></small><?php endif; ?>
                </div>
            </div></td>
            <td><div><?php echo e($user->email ?: 'Chưa có email'); ?></div><small class="text-muted"><?php echo e($user->phone ?: 'Chưa có số điện thoại'); ?></small></td>
            <td><span class="badge bg-primary"><?php echo e($roles[$role] ?? $role); ?></span></td>
            <td><span class="badge <?php echo e($user->status === 'active' ? 'bg-success' : 'bg-danger'); ?>"><?php echo e($user->status === 'active' ? 'Hoạt động' : 'Đã khóa'); ?></span></td>
            <td><small class="d-block">Email: <?php echo e($user->email_verified_at ? 'Đã xác thực' : 'Chưa xác thực'); ?></small>
                <?php if(in_array($role, ['teacher', 'center'])): ?><small class="d-block">Hồ sơ: <?php echo e(['unverified'=>'Chưa xác minh', 'pending'=>'Chờ duyệt', 'verified'=>'Đã xác minh', 'rejected'=>'Bị từ chối'][$profile?->verification_status ?? 'unverified'] ?? 'Chưa xác minh'); ?></small><?php endif; ?>
            </td>
            <td class="text-nowrap"><?php echo e($user->created_at?->format('d/m/Y H:i')); ?></td>
            <td><div class="d-flex gap-1"><a class="btn btn-sm btn-outline-primary" href="<?php echo e(route('admin.users.show', $user)); ?>">Chi tiết</a><a class="btn btn-sm btn-warning" href="<?php echo e(route('admin.users.edit', $user)); ?>">Sửa</a>
                <?php if (! ($user->is(auth()->user()))): ?><form method="POST" action="<?php echo e(route('admin.users.destroy', $user)); ?>" onsubmit="return confirm('Xóa tài khoản và thu hồi đăng nhập? Email và số điện thoại sẽ dùng lại được cho tài khoản mới.')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="btn btn-sm btn-danger">Xóa</button></form><?php endif; ?>
            </div></td>
        </tr>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="8" class="text-center p-4">Không tìm thấy tài khoản phù hợp.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<p class="text-muted">Tổng cộng <?php echo e($users->total()); ?> tài khoản.</p>
<?php echo e($users->links('pagination::bootstrap-5')); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\admin_edumatchv1\resources\views/admin/user/index.blade.php ENDPATH**/ ?>