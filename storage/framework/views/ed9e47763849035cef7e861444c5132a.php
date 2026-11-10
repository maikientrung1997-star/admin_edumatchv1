<?php $__env->startSection('title', 'Cấp lớp - EduMatch'); ?>
<?php $__env->startSection('content'); ?>
<h1><?php echo e($level->exists ? 'Chỉnh sửa cấp lớp' : 'Thêm cấp lớp'); ?></h1><form class="card p-4" method="POST" action="<?php echo e($level->exists ? route('admin.education-levels.update', $level) : route('admin.education-levels.store')); ?>"><?php echo csrf_field(); ?> <?php if($level->exists): ?> <?php echo method_field('PUT'); ?> <?php endif; ?>
<div class="mb-3"><label class="form-label" for="code">Mã cấp lớp</label><input class="form-control" name="code" id="code" value="<?php echo e(old('code', $level->code)); ?>" maxlength="30" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="grade-1, dai-hoc" required></div>
<div class="mb-3"><label class="form-label" for="name">Tên hiển thị</label><input class="form-control" name="name" id="name" value="<?php echo e(old('name', $level->name)); ?>" maxlength="100" required></div>
<div class="mb-3"><label class="form-label" for="sort_order">Thứ tự</label><input class="form-control" name="sort_order" id="sort_order" type="number" value="<?php echo e(old('sort_order', $level->sort_order ?? 0)); ?>" min="0" max="1000000" required></div>
<input type="hidden" name="is_active" value="0"><label class="mb-4"><input type="checkbox" name="is_active" value="1" <?php if(old('is_active', $level->is_active ?? true)): echo 'checked'; endif; ?>> Kích hoạt hiển thị</label>
<div><button class="btn btn-primary">Lưu cấp lớp</button> <a class="btn btn-secondary" href="<?php echo e(route('admin.education-levels.index')); ?>">Quay lại</a></div></form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/admin_edumatchv1/resources/views/admin/education_levels/form.blade.php ENDPATH**/ ?>