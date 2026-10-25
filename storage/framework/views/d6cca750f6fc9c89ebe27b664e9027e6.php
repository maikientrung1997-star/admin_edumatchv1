<?php $__env->startSection('title', 'Trang đăng nhập'); ?>
<?php $__env->startSection('content'); ?>
    <style>
        body {
            background-color: #f8f9fa;
        }

        .register-container {
            max-width: 400px;
            margin: 65px auto;
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .password-container {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }
    </style>
    <div class="container">
        <div class="register-container">
            <h3 class="text-center">Đăng nhập</h3>
            <form action="<?php echo e(route('login')); ?>" method="POST">
                <?php echo csrf_field(); ?>
                <?php if($errors->any()): ?>
                    <div class="alert alert-danger">
                        <ul>
                            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li><?php echo e($error); ?></li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if(session('error')): ?>
                    <div class="alert alert-danger">
                        <?php echo e(session('error')); ?>

                    </div>
                <?php endif; ?>

                <?php if(session('success')): ?>
                    <div class="alert alert-success">
                        <?php echo e(session('success')); ?>

                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label for="login" class="form-label">Email hoặc Số điện thoại <span
                            class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="login" name="login"
                        placeholder="Nhập email hoặc số điện thoại" value="<?php echo e(old('login')); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                    <div class="password-container d-flex">
                        <input type="password" value="<?php echo e(old('password')); ?>" class="form-control" name="password"
                            id="password" placeholder="Nhập mật khẩu" required>
                        <i class="bi bi-eye-slash toggle-password" id="togglePassword"></i>
                    </div>
                </div>

                <div class="mb-3">
                    <input type="checkbox" name="remember" id="remember">
                    <label for="password" class="form-label">Ghi nhớ tài khoản </label>
                </div>

                <button type="submit" class="btn btn-primary w-100">Đăng nhập</button>

            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Xử lý sự kiện khi click vào mắt mật khẩu
            document.querySelectorAll('.toggle-password').forEach(item => {
                item.addEventListener('click', function () {
                    let input = this.previousElementSibling;
                    if (input.type === "password") {
                        input.type = "text";
                        this.classList.remove("bi-eye-slash");
                        this.classList.add("bi-eye");
                    } else {
                        input.type = "password";
                        this.classList.remove("bi-eye");
                        this.classList.add("bi-eye-slash");
                    }
                });
            });
        });
    </script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\laragon\www\admin_edumatchv1\resources\views/login.blade.php ENDPATH**/ ?>