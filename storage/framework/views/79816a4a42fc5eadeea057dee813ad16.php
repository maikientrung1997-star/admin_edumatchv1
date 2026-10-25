<?php
    use Carbon\Carbon;
    $AppSetting = \App\Models\AppSetting::first();
    $notifications = [];
    $Countnotifications = 0;
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title><?php echo $__env->yieldContent('title', 'Quản trị EduMatch'); ?></title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <link rel="icon"
        href="<?php echo e($AppSetting?->logo_path ? asset('storage/' . $AppSetting->logo_path) : asset('storage/logo-default.png')); ?>"
        type="image/x-icon" />

    <!-- Fonts and icons -->
    <script src="/assets/js/plugin/webfont/webfont.min.js"></script>
    <script>
        WebFont.load({
            google: {
                families: ["Public Sans:300,400,500,600,700"]
            },
            custom: {
                families: [
                    "Font Awesome 5 Solid",
                    "Font Awesome 5 Regular",
                    "Font Awesome 5 Brands",
                    "simple-line-icons",
                ],
                urls: ["/assets/css/fonts.min.css"],
            },
            active: function () {
                sessionStorage.fonts = true;
            },
        });
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <!-- CSS Files -->
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="/assets/css/plugins.min.css" />
    <link rel="stylesheet" href="/assets/css/kaiadmin.min.css" />

    <!-- CSS Just for demo purpose, don't include it in your project -->
    <link rel="stylesheet" href="/assets/css/demo.css" />
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <style>
        ul {
            margin: 0;
        }

        .form-label {
            font-weight: bold
        }

        /* Summernote UI Fixes */
        .note-editor.note-frame {
            background-color: #fff !important;
        }

        .note-editor.note-frame.fullscreen {
            background-color: #fff !important;
            z-index: 1050;
        }

        .note-editable {
            background-color: #fff !important;
        }

        .note-modal-footer .note-btn {
            background-color: #007bff !important;
            color: #fff !important;
            padding: 5px 15px !important;
            margin: 5px !important;
        }

        .note-modal-footer .note-btn-primary {
            background-color: #28a745 !important;
        }

        .note-modal-footer {
            height: 60px !important;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar" data-background-color="dark">
            <div class="sidebar-logo">
                <!-- Logo Header -->
                <div class="logo-header" data-background-color="dark">
                    <a href="/admin" class="logo">
                        <img src="<?php echo e($AppSetting?->logo_path ? asset('storage/' . $AppSetting->logo_path) : asset('storage/logo-default.png')); ?>"
                            alt="navbar brand" class="navbar-brand" height="60" style="border-radius: 50%; " />
                    </a>
                    <div class="nav-toggle">
                        <button class="btn btn-toggle toggle-sidebar">
                            <i class="gg-menu-right"></i>
                        </button>
                        <button class="btn btn-toggle sidenav-toggler">
                            <i class="gg-menu-left"></i>
                        </button>
                    </div>
                    <button class="topbar-toggler more">
                        <i class="gg-more-vertical-alt"></i>
                    </button>
                </div>
                <!-- End Logo Header -->
            </div>
            <div class="sidebar-wrapper scrollbar scrollbar-inner">
                <div class="sidebar-content">
                    <ul class="nav nav-secondary">
                        <li class="nav-item <?php echo e(request()->routeIs('admin') ? 'active' : ''); ?>">
                            <a href="<?php echo e(route('admin')); ?>" class="collapsed" aria-expanded="false">
                                <i class="fas fa-home"></i>
                                <p>Trang chủ</p>
                            </a>
                        </li>

                        <?php if(auth()->user()?->hasAnyRole(['super_admin', 'admin'])): ?>
                        <li class="nav-section"><h4 class="text-section">Đào tạo &amp; Danh mục</h4></li>
                        <li class="nav-item <?php echo e(request()->routeIs('admin.subjects.*') ? 'active' : ''); ?>"><a href="<?php echo e(route('admin.subjects.index')); ?>"><i class="fas fa-book"></i><p>Quản lý Môn học</p></a></li>
                        <li class="nav-item <?php echo e(request()->routeIs('admin.education-levels.*') ? 'active' : ''); ?>"><a href="<?php echo e(route('admin.education-levels.index')); ?>"><i class="fas fa-graduation-cap"></i><p>Quản lý Cấp lớp</p></a></li>
                        <li class="nav-item <?php echo e(request()->routeIs('admin.administrative-areas.*') ? 'active' : ''); ?>"><a href="<?php echo e(route('admin.administrative-areas.index')); ?>"><i class="fas fa-map-marked-alt"></i><p>Tra cứu khu vực</p></a></li>
                        <?php ($pendingVerificationCount = \App\Models\VerificationRequest::where('status', 'pending')->count()); ?>
                        <li class="nav-section"><h4 class="text-section">Kiểm duyệt &amp; Uy tín</h4></li>
                        <li class="nav-item <?php echo e(request()->routeIs('admin.verifications.*') ? 'active' : ''); ?>"><a data-bs-toggle="collapse" href="#verificationsCollapse" aria-expanded="<?php echo e(request()->routeIs('admin.verifications.*') ? 'true' : 'false'); ?>"><i class="fas fa-user-check"></i><p>Trung tâm xác minh</p><?php if($pendingVerificationCount): ?><span class="badge bg-warning text-dark ms-auto"><?php echo e($pendingVerificationCount); ?></span><?php else: ?><span class="caret"></span><?php endif; ?></a><div id="verificationsCollapse" class="collapse <?php echo e(request()->routeIs('admin.verifications.*') ? 'show' : ''); ?>"><ul class="nav nav-collapse"><?php $__currentLoopData = ['index' => 'Tất cả yêu cầu', 'teachers' => 'Xác minh Giáo viên', 'centers' => 'Xác minh Trung tâm']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $action => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li class="<?php echo e(request()->routeIs('admin.verifications.'.$action) ? 'active' : ''); ?>"><a href="<?php echo e(route('admin.verifications.'.$action)); ?>"><span class="sub-item"><?php echo e($label); ?></span></a></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div></li>
                        <?php endif; ?>
                        <li class="nav-section">
                            <span class="sidebar-mini-icon">
                                <i class="fa fa-ellipsis-h"></i>
                            </span>
                            <h4 class="text-section">Cửa hàng</h4>
                        </li>

                        <?php if (\Illuminate\Support\Facades\Blade::check('role', 'admin')): ?>
                        
                        <li class="nav-item <?php echo e(request()->routeIs('admin.app-setting.*') ? 'active' : ''); ?>">
                            <a href="<?php echo e(route('admin.app-setting.index')); ?>">
                                <i class="fas fa-info-circle"></i>
                                <p>Thông tin ứng dụng</p>
                            </a>
                        </li>
                        <?php endif; ?>

                        
                        <li class="nav-item <?php echo e(request()->routeIs('admin.banners.*') ? 'active' : ''); ?>">
                            <a data-bs-toggle="collapse" href="#banners"
                                aria-expanded="<?php echo e(request()->routeIs('admin.banners.*') ? 'true' : 'false'); ?>">
                                <i class="far fa-images"></i>
                                <p>Quản lý banner</p>
                                <span class="caret"></span>
                            </a>
                            <div class="collapse <?php echo e(request()->routeIs('admin.banners.*') ? 'show' : ''); ?>"
                                id="banners">
                                <ul class="nav nav-collapse">
                                    <li class="<?php echo e(request()->routeIs('admin.banners.index') ? 'active' : ''); ?>">
                                        <a href="<?php echo e(route('admin.banners.index')); ?>">
                                            <span class="sub-item">Danh sách banner</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>


                        <li class="nav-section">
                            <span class="sidebar-mini-icon">
                                <i class="fa fa-ellipsis-h"></i>
                            </span>
                            <h4 class="text-section">Tin tức</h4>
                        </li>

                        <li class="nav-item <?php echo e(request()->routeIs('admin.categories.*') ? 'active' : ''); ?>">
                            <a data-bs-toggle="collapse" href="#Category"
                                aria-expanded="<?php echo e(request()->routeIs('admin.categories.*') ? 'true' : 'false'); ?>">
                                <i class="fas fa-folder"></i>
                                <p>Danh mục bài viết</p>
                                <span class="caret"></span>
                            </a>
                            <div class="collapse <?php echo e(request()->routeIs('admin.categories.*') ? 'show' : ''); ?>"
                                id="Category">
                                <ul class="nav nav-collapse">
                                    <li class="<?php echo e(request()->routeIs('admin.categories.index') ? 'active' : ''); ?>">
                                        <a href="<?php echo e(route('admin.categories.index')); ?>">
                                            <span class="sub-item">Danh sách danh mục</span>
                                        </a>
                                    </li>
                                    <li class="<?php echo e(request()->routeIs('admin.categories.create') ? 'active' : ''); ?>">
                                        <a href="<?php echo e(route('admin.categories.create')); ?>">
                                            <span class="sub-item">Thêm mới danh mục</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <li class="nav-item <?php echo e(request()->routeIs('admin.articles.*') ? 'active' : ''); ?>">
                            <a data-bs-toggle="collapse" href="#Aritcles"
                                aria-expanded="<?php echo e(request()->routeIs('admin.articles.*') ? 'true' : 'false'); ?>">
                                <i class="fas fa-newspaper"></i>
                                <p>Quản lý bài viết</p>
                                <span class="caret"></span>
                            </a>
                            <div class="collapse <?php echo e(request()->routeIs('admin.articles.*') ? 'show' : ''); ?>"
                                id="Aritcles">
                                <ul class="nav nav-collapse">
                                    <li class="<?php echo e(request()->routeIs('admin.articles.index') ? 'active' : ''); ?>">
                                        <a href="<?php echo e(route('admin.articles.index')); ?>">
                                            <span class="sub-item">Danh sách bài viết</span>
                                        </a>
                                    </li>
                                    <li class="<?php echo e(request()->routeIs('admin.articles.create') ? 'active' : ''); ?>">
                                        <a href="<?php echo e(route('admin.articles.create')); ?>">
                                            <span class="sub-item">Thêm mới bài viết</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <?php if (\Illuminate\Support\Facades\Blade::check('role', 'admin')): ?>
                        <li class="nav-section">
                            <span class="sidebar-mini-icon">
                                <i class="fa fa-ellipsis-h"></i>
                            </span>
                            <h4 class="text-section">Phân quyền</h4>
                        </li>
                        <li
                            class="nav-item <?php echo e(request()->routeIs('admin.roles.*') || request()->routeIs('admin.permissions.*') ? 'active' : ''); ?>">
                            <a data-bs-toggle="collapse" href="#auth">
                                <i class="fas fa-user-shield"></i>
                                <p>Quản lý phân quyền</p>
                                <span class="caret"></span>
                            </a>
                            <div class="collapse <?php echo e(request()->routeIs('admin.roles.*') || request()->routeIs('admin.permissions.*') ? 'show' : ''); ?>"
                                id="auth">
                                <ul class="nav nav-collapse">
                                    <li class="<?php echo e(request()->routeIs('admin.roles.index') ? 'active' : ''); ?>">
                                        <a href="<?php echo e(route('admin.roles.index')); ?>">
                                            <span class="sub-item">Vai trò (Roles)</span>
                                        </a>
                                    </li>
                                    <li class="<?php echo e(request()->routeIs('admin.permissions.index') ? 'active' : ''); ?>">
                                        <a href="<?php echo e(route('admin.permissions.index')); ?>">
                                            <span class="sub-item">Quyền hạn (Permissions)</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <?php endif; ?>

                        <?php if (\Illuminate\Support\Facades\Blade::check('hasanyrole', 'super_admin|admin')): ?>
                        <li class="nav-section">
                            <span class="sidebar-mini-icon">
                                <i class="fa fa-ellipsis-h"></i>
                            </span>
                            <h4 class="text-section">Tài khoản</h4>
                        </li>
                        <li class="nav-item <?php echo e(request()->routeIs('admin.users.*') ? 'active' : ''); ?>">
                            <a data-bs-toggle="collapse" href="#users">
                                <i class="fas fa-users-cog"></i>
                                <p>Quản lý tài khoản</p>
                                <span class="caret"></span>
                            </a>
                            <div class="collapse <?php echo e(request()->routeIs('admin.users.*') ? 'show' : ''); ?>" id="users">
                                <ul class="nav nav-collapse">
                                    <li class="<?php echo e(request()->routeIs('admin.users.index') ? 'active' : ''); ?>">
                                        <a href="<?php echo e(route('admin.users.index')); ?>">
                                            <span class="sub-item">Danh sách tài khoản</span>
                                        </a>
                                    </li>
                                    <li class="<?php echo e(request()->routeIs('admin.users.create') ? 'active' : ''); ?>">
                                        <a href="<?php echo e(route('admin.users.create')); ?>">
                                            <span class="sub-item">Thêm mới tài khoản</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <?php endif; ?>

                        <li class="nav-item">
                            <a href="/logout">
                                <i class="fas fa-sign-out-alt"></i>
                                <p>Đăng xuất</p>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- End Sidebar -->

        <div class="main-panel">
            <div class="main-header">
                <div class="main-header-logo">
                    <!-- Logo Header -->
                    <div class="logo-header" data-background-color="dark">
                        <a href="/admin" class="logo">
                            <img src="<?php echo e($AppSetting?->logo_path ? asset('storage/' . $AppSetting->logo_path) : asset('storage/logo-default.png')); ?>"
                                alt="navbar brand" class="navbar-brand" height="60" style=" border-radius: 50%; " />
                        </a>
                        <div class="nav-toggle">
                            <button class="btn btn-toggle toggle-sidebar">
                                <i class="gg-menu-right"></i>
                            </button>
                            <button class="btn btn-toggle sidenav-toggler">
                                <i class="gg-menu-left"></i>
                            </button>
                        </div>
                        <button class="topbar-toggler more">
                            <i class="gg-more-vertical-alt"></i>
                        </button>
                    </div>
                </div>
                <nav class="navbar navbar-header navbar-header-transparent navbar-expand-lg border-bottom">
                    <div class="container-fluid">
                        <form action="<?php echo e(route('admin.search')); ?>" method="GET"
                            class="navbar navbar-header-left navbar-expand-lg navbar-form nav-search p-0 d-none d-lg-flex">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <button type="submit" class="btn btn-search pe-1">
                                        <i class="fa fa-search search-icon"></i>
                                    </button>
                                </div>
                                <input type="text" name="search" value="<?php echo e(request('search')); ?>"
                                    placeholder="Search ..." class="form-control" />
                            </div>
                        </form>

                        <ul class="navbar-nav topbar-nav ms-md-auto align-items-center">
                            <li class="nav-item topbar-icon dropdown hidden-caret d-flex d-lg-none">
                                <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button"
                                    aria-expanded="false" aria-haspopup="true">
                                    <i class="fa fa-search"></i>
                                </a>
                                <ul class="dropdown-menu dropdown-search animated fadeIn">
                                    <form action="<?php echo e(route('admin.search')); ?>" method="GET"
                                        class="navbar-left navbar-form nav-search">
                                        <div class="input-group">
                                            <input type="text" name="search" value="<?php echo e(request('search')); ?>"
                                                placeholder="Search ..." class="form-control" />
                                        </div>
                                    </form>
                                </ul>
                            </li>
                            <li class="nav-item topbar-icon dropdown hidden-caret">
                                <a class="nav-link dropdown-toggle" href="#" id="notifDropdown" role="button"
                                    data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fa fa-bell"></i>
                                    <span class="notification"><?php echo e($Countnotifications ?? 0); ?></span>
                                </a>
                                <ul class="dropdown-menu notif-box animated fadeIn" aria-labelledby="notifDropdown">
                                    <li>
                                        <div class="dropdown-title">
                                            Bạn có <?php echo e($Countnotifications ?? 0); ?> thông báo
                                        </div>
                                    </li>
                                    <li>
                                        <div class="notif-scroll scrollbar-outer">
                                            <div class="notif-center">
                                                <?php if(isset($notifications) && count($notifications) > 0): ?>
                                                    <?php $__currentLoopData = $notifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                        <a href="#">
                                                            <div class="notif-icon notif-primary" style="min-width: 40px">
                                                                <i class="fas fa-cart-plus"></i>
                                                            </div>
                                                            <div class="notif-content">
                                                                <span class="block">
                                                                    <?php echo e(is_object($notification) ? $notification->message : ($notification['message'] ?? '-')); ?>

                                                                </span>
                                                                <span
                                                                    class="time"><?php echo e(\Carbon\Carbon::parse(is_object($notification) ? $notification->created_at : ($notification['created_at'] ?? now()))->diffInMinutes()); ?>

                                                                    phút trước</span>
                                                            </div>
                                                        </a>
                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                <?php else: ?>
                                                    <div class="text-center p-3">Không có thông báo mới</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </li>

                                </ul>
                            </li>

                            <li class="nav-item topbar-user dropdown hidden-caret">
                                <a class="dropdown-toggle profile-pic" data-bs-toggle="dropdown" href="#"
                                    aria-expanded="false">
                                    <div class="avatar-sm">
                                        <img src="<?php echo e(Auth::user()->avatar ? asset('storage/' . Auth::user()->avatar) : 'https://static.vecteezy.com/system/resources/previews/009/292/244/non_2x/default-avatar-icon-of-social-media-user-vector.jpg'); ?>"
                                            alt="..." class="avatar-img rounded-circle" />
                                    </div>
                                    <span class="profile-username">
                                        <span class="op-7">Hi,</span>
                                        <span class="fw-bold"><?php echo e(Auth::user()->name ?? Auth::user()->full_name); ?></span>
                                    </span>
                                </a>
                                <ul class="dropdown-menu dropdown-user animated fadeIn">
                                    <div class="dropdown-user-scroll scrollbar-outer">
                                        <li>
                                            <div class="user-box">
                                                <div class="avatar-lg">
                                                    <img src="<?php echo e(Auth::user()->avatar ? asset('storage/' . Auth::user()->avatar) : 'https://static.vecteezy.com/system/resources/previews/009/292/244/non_2x/default-avatar-icon-of-social-media-user-vector.jpg'); ?>"
                                                        alt="image profile" class="avatar-img rounded" />
                                                </div>
                                                <div class="u-text">
                                                    <h4><?php echo e(Auth::user()->name ?? Auth::user()->full_name); ?></h4>
                                                    <p class="text-muted"><?php echo e(Auth::user()->email); ?></p>
                                                    <a href="<?php echo e(route('admin.profile.index')); ?>"
                                                        class="btn btn-xs btn-secondary btn-sm">View
                                                        Profile</a>
                                                </div>
                                            </div>
                                        </li>
                                        <li>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="<?php echo e(route('logout')); ?>">Logout</a>
                                        </li>
                                    </div>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>
                <!-- End Navbar -->
            </div>

            <div class="container">
                <div class="page-inner">
                    <?php if($errors->any()): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0">
                                <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <li><?php echo e($error); ?></li>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if(session('success')): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo e(session('success')); ?>

                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <script>
                        // Tự động tắt thông báo sau 5 giây
                        document.addEventListener('DOMContentLoaded', function () {
                            const alerts = document.querySelectorAll('.alert-dismissible');
                            alerts.forEach(function (alert) {
                                setTimeout(function () {
                                    const bsAlert = new bootstrap.Alert(alert);
                                    bsAlert.close();
                                }, 5000);
                            });
                        });
                    </script>


                    <?php echo $__env->yieldContent('content'); ?>
                </div>
            </div>

            
        </div>

        <div class="custom-template">
            <div class="title">Settings</div>
            <div class="custom-content">
                <div class="switcher">
                    <div class="switch-block">
                        <h4>Logo Header</h4>
                        <div class="btnSwitch">
                            <button type="button" class="selected changeLogoHeaderColor" data-color="dark"></button>
                            <button type="button" class="changeLogoHeaderColor" data-color="blue"></button>
                            <button type="button" class="changeLogoHeaderColor" data-color="purple"></button>
                            <button type="button" class="changeLogoHeaderColor" data-color="light-blue"></button>
                            <button type="button" class="changeLogoHeaderColor" data-color="green"></button>
                            <button type="button" class="changeLogoHeaderColor" data-color="orange"></button>
                            <button type="button" class="changeLogoHeaderColor" data-color="red"></button>
                            <button type="button" class="changeLogoHeaderColor" data-color="white"></button>
                            <br />
                            <button type="button" class="changeLogoHeaderColor" data-color="dark2"></button>
                            <button type="button" class="changeLogoHeaderColor" data-color="blue2"></button>
                            <button type="button" class="changeLogoHeaderColor" data-color="purple2"></button>
                            <button type="button" class="changeLogoHeaderColor" data-color="light-blue2"></button>
                            <button type="button" class="changeLogoHeaderColor" data-color="green2"></button>
                            <button type="button" class="changeLogoHeaderColor" data-color="orange2"></button>
                            <button type="button" class="changeLogoHeaderColor" data-color="red2"></button>
                        </div>
                    </div>
                    <div class="switch-block">
                        <h4>Navbar Header</h4>
                        <div class="btnSwitch">
                            <button type="button" class="changeTopBarColor" data-color="dark"></button>
                            <button type="button" class="changeTopBarColor" data-color="blue"></button>
                            <button type="button" class="changeTopBarColor" data-color="purple"></button>
                            <button type="button" class="changeTopBarColor" data-color="light-blue"></button>
                            <button type="button" class="changeTopBarColor" data-color="green"></button>
                            <button type="button" class="changeTopBarColor" data-color="orange"></button>
                            <button type="button" class="changeTopBarColor" data-color="red"></button>
                            <button type="button" class="selected changeTopBarColor" data-color="white"></button>
                            <br />
                            <button type="button" class="changeTopBarColor" data-color="dark2"></button>
                            <button type="button" class="changeTopBarColor" data-color="blue2"></button>
                            <button type="button" class="changeTopBarColor" data-color="purple2"></button>
                            <button type="button" class="changeTopBarColor" data-color="light-blue2"></button>
                            <button type="button" class="changeTopBarColor" data-color="green2"></button>
                            <button type="button" class="changeTopBarColor" data-color="orange2"></button>
                            <button type="button" class="changeTopBarColor" data-color="red2"></button>
                        </div>
                    </div>
                    <div class="switch-block">
                        <h4>Sidebar</h4>
                        <div class="btnSwitch">
                            <button type="button" class="changeSideBarColor" data-color="white"></button>
                            <button type="button" class="selected changeSideBarColor" data-color="dark"></button>
                            <button type="button" class="changeSideBarColor" data-color="dark2"></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="custom-toggle">
                <i class="icon-settings"></i>
            </div>
        </div>
    </div>
    <script>
        window.AppSetting = {
            logoLight: "<?php echo e($AppSetting?->logo_path ? asset('storage/' . $AppSetting->logo_path) : asset('storage/logo-default.png')); ?>",
            logoDark: "<?php echo e($AppSetting?->logo_path ? asset('storage/' . $AppSetting->logo_path) : asset('storage/logo-default.png')); ?>"
        };
    </script>
    <!--   Core JS Files   -->
    <script src="/assets/js/core/jquery-3.7.1.min.js"></script>
    <script src="/assets/js/core/popper.min.js"></script>
    <script src="/assets/js/core/bootstrap.min.js"></script>

    <!-- jQuery Scrollbar -->
    <script src="/assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>

    <!-- Chart JS -->
    <script src="/assets/js/plugin/chart.js/chart.min.js"></script>

    <!-- jQuery Sparkline -->
    <script src="/assets/js/plugin/jquery.sparkline/jquery.sparkline.min.js"></script>

    <!-- Chart Circle -->
    <script src="/assets/js/plugin/chart-circle/circles.min.js"></script>

    <!-- Datatables -->
    <script src="/assets/js/plugin/datatables/datatables.min.js"></script>

    <!-- Bootstrap Notify -->
    <script src="/assets/js/plugin/bootstrap-notify/bootstrap-notify.min.js"></script>

    <!-- jQuery Vector Maps -->
    <script src="/assets/js/plugin/jsvectormap/jsvectormap.min.js"></script>
    <script src="/assets/js/plugin/jsvectormap/world.js"></script>

    <!-- Sweet Alert -->
    <script src="/assets/js/plugin/sweetalert/sweetalert.min.js"></script>

    <!-- Kaiadmin JS -->
    <script src="/assets/js/kaiadmin.min.js"></script>

    
    <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
    <!-- Thêm Axios từ CDN -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <script>
        <?php if($AppSetting && $AppSetting->logo_path): ?>
            document.querySelectorAll("#logo_top, .logo-header").forEach(img => {
                img.src = "<?php echo e(asset('storage/' . $AppSetting->logo_path)); ?>";
            });
        <?php endif; ?>



        Pusher.logToConsole = true;

        var pusher = new Pusher('ecc5ac907199f6950c31', {
            cluster: 'ap1'
        });

        var channel = pusher.subscribe('orders');
        channel.bind('new.order.placed', function (data) {
            console.log(data);
            location.reload();

            $.notify({
                icon: 'icon-bell',
                title: 'Đơn hàng mới',
                message: 'Bạn có 1 đơn hàng mới từ' + data.order.name,
            }, {
                type: 'secondary',
                placement: {
                    from: "bottom",
                    align: "right"
                },
                time: 3000,
            });
            const data2 = {
                message: "Bạn có một đơn hàng mới từ " + data.order.name,
                order_id: data.order.id
            };

            axios.post('/admin/notifications', data2)
                .then(response => {
                    console.log('Thông báo đã được thêm:', response);

                })
                .catch(error => {
                    console.error('Lỗi khi thêm thông báo:', error);
                });


        });
        $("#lineChart").sparkline([102, 109, 120, 99, 110, 105, 115], {
            type: "line",
            height: "70",
            width: "100%",
            lineWidth: "2",
            lineColor: "#177dff",
            fillColor: "rgba(23, 125, 255, 0.14)",
        });

        $("#lineChart2").sparkline([99, 125, 122, 105, 110, 124, 115], {
            type: "line",
            height: "70",
            width: "100%",
            lineWidth: "2",
            lineColor: "#f3545d",
            fillColor: "rgba(243, 84, 93, .14)",
        });

        $("#lineChart3").sparkline([105, 103, 123, 100, 95, 105, 115], {
            type: "line",
            height: "70",
            width: "100%",
            lineWidth: "2",
            lineColor: "#ffa534",
            fillColor: "rgba(255, 165, 52, .14)",
        });

        function confirmDelete(formId, message = 'Bạn có chắc chắn muốn xóa không?') {
            swal({
                title: 'Xác nhận xóa',
                text: message,
                icon: 'warning',
                buttons: {
                    cancel: {
                        text: 'Hủy',
                        visible: true,
                        className: 'btn btn-secondary'
                    },
                    confirm: {
                        text: 'Xóa',
                        className: 'btn btn-danger'
                    }
                }
            }).then((willDelete) => {
                if (willDelete) {
                    document.getElementById(formId).submit();
                }
            });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>

</html>
<?php /**PATH C:\laragon\www\admin_edumatchv1\resources\views/admin/layout.blade.php ENDPATH**/ ?>