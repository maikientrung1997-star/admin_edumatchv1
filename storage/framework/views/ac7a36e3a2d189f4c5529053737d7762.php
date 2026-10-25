<?php
    $AppSetting = \App\Models\AppSetting::first();
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $__env->yieldContent('title'); ?></title>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo e($AppSetting->app_name ?? config('app.name')); ?>">
    <meta property="og:description" content="<?php echo e($AppSetting->description ?? 'Mô tả mặc định'); ?>">
    <meta property="og:image"
        content="<?php echo e($AppSetting?->logo_path ? asset('storage/' . $AppSetting->logo_path) : asset('storage/logo-default.png')); ?>">
    <link rel="icon"
        href="<?php echo e($AppSetting?->favicon_path ? asset('storage/' . $AppSetting->favicon_path) : asset('storage/logo-default.png')); ?>"
        type="image/x-icon" />

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f2f4f8;
            font-family: 'Arial', sans-serif;
            padding-bottom: 80px;
            margin: 0;
        }

        img {
            width: 100%;
        }

        .header-fixed {
            position: sticky;
            top: 0;
            z-index: 1000;
            background-color: #fff;
            padding: 10px 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .banner img {
            width: 100%;
            border-radius: 10px;
        }

        .member-box,
        .wallet-box,
        .product-select-box {
            background: #fff;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .wallet-amount {
            /* font-size: 28px; */
            font-weight: bold;
            color: #152379;
        }

        .btn-rounded {
            border-radius: 50px;
        }

        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #152379;
            border-top: 1px solid #ddd;
            z-index: 999;
            display: flex;
            justify-content: space-around;
            padding: 10px 0;
        }

        .bottom-nav a {
            color: white;
            text-align: center;
            font-size: 13px;
            text-decoration: none;
        }

        .bottom-nav a .fa {
            display: block;
            font-size: 20px;
            margin-bottom: 3px;
        }

        .badge-noti {
            font-size: 10px;
            position: absolute;
            top: -3px;
            right: -6px;
        }

        .nav-icons {
            position: relative;
        }
    </style>
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>

</head>

<body>

    <?php echo $__env->yieldContent('content'); ?>
    <?php if(session('success') || session('error')): ?>
        <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999">
            <div id="liveToast"
                class="toast align-items-center text-white <?php echo e(session('success') ? 'bg-success' : 'bg-danger'); ?> border-0 show"
                role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <?php echo e(session('success') ?? session('error')); ?>

                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                        aria-label="Close"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const toastEl = document.getElementById('liveToast');
            if (toastEl) {
                const toast = new bootstrap.Toast(toastEl);
                toast.show();
            }
        });
    </script>

</body>

</html>
<?php /**PATH C:\laragon\www\admin_edumatchv1\resources\views/layout.blade.php ENDPATH**/ ?>