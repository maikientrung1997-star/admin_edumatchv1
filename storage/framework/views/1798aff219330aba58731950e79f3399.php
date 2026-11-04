<?php $__env->startSection('title', 'Môn học - EduMatch'); ?>
<?php $__env->startSection('content'); ?>
<h1><?php echo e($subject->exists ? 'Chỉnh sửa môn học' : 'Thêm mới môn học'); ?></h1>
<form class="card p-4" method="POST" enctype="multipart/form-data" action="<?php echo e($subject->exists ? route('admin.subjects.update', $subject) : route('admin.subjects.store')); ?>"><?php echo csrf_field(); ?> <?php if($subject->exists): ?> <?php echo method_field('PUT'); ?> <?php endif; ?>
<div class="mb-3"><label class="form-label" for="name">Tên môn học</label><input class="form-control" id="name" name="name" value="<?php echo e(old('name', $subject->name)); ?>" required maxlength="150"></div>
<div class="mb-3"><label class="form-label" for="slug">Slug</label><input class="form-control" id="slug" name="slug" value="<?php echo e(old('slug', $subject->slug)); ?>" maxlength="180" placeholder="Tự động tạo từ tên môn học"></div>
<div class="mb-3"><label class="form-label" for="icon">Icon JPG, PNG, WebP hoặc SVG (tối đa 2 MB)</label><input class="form-control" type="file" id="icon" name="icon" accept="image/jpeg,image/png,image/webp,image/svg+xml"><img id="icon-preview" src="<?php echo e($subject->icon_path ? asset('storage/'.$subject->icon_path) : ''); ?>" alt="Xem trước icon" width="90" class="mt-2" <?php if(!$subject->icon_path): ?> hidden <?php endif; ?>></div>
<div class="mb-3"><label class="form-label" for="sort_order">Thứ tự hiển thị</label><input class="form-control" type="number" id="sort_order" name="sort_order" value="<?php echo e(old('sort_order', $subject->sort_order ?? 0)); ?>" min="0" max="1000000" required></div>
<input type="hidden" name="is_active" value="0"><label class="mb-4"><input type="checkbox" name="is_active" value="1" <?php if(old('is_active', $subject->is_active ?? true)): echo 'checked'; endif; ?>> Kích hoạt hiển thị</label>
<div><button class="btn btn-primary">Lưu môn học</button> <a class="btn btn-secondary" href="<?php echo e(route('admin.subjects.index')); ?>">Quay lại</a></div></form>
<?php $__env->stopSection(); ?>
<?php $__env->startPush('scripts'); ?>
<script>
const subjectName = document.getElementById('name'), subjectSlug = document.getElementById('slug');
let customSlug = Boolean(subjectSlug.value);
subjectSlug.addEventListener('input', () => customSlug = Boolean(subjectSlug.value));
subjectName.addEventListener('input', () => { if (!customSlug) subjectSlug.value = subjectName.value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[đĐ]/g, 'd').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); });
document.getElementById('icon').addEventListener('change', event => { const file = event.target.files[0]; if (!file) return; const preview = document.getElementById('icon-preview'); if (preview.dataset.objectUrl) URL.revokeObjectURL(preview.dataset.objectUrl); preview.src = preview.dataset.objectUrl = URL.createObjectURL(file); preview.hidden = false; });
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('admin.layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/admin_edumatchv1/resources/views/admin/subjects/form.blade.php ENDPATH**/ ?>