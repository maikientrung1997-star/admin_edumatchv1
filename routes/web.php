<?php

use App\Http\Controllers\AppSettingController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [AppSettingController::class, 'openApp'])->name('admin');
    Route::get('/admin', [AppSettingController::class, 'openApp'])->name('admin');

    Route::get('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::prefix('admin')->name('admin.')->group(function () {
        // Admin only
        Route::middleware(['role:admin'])->group(function () {
            Route::get('app-setting', [AppSettingController::class, 'index'])->name('app-setting.index');
            Route::put('app-setting', [AppSettingController::class, 'update'])->name('app-setting.store');
            Route::resource('roles', \App\Http\Controllers\RoleController::class);
            Route::resource('permissions', \App\Http\Controllers\PermissionController::class);
        });

        Route::middleware(\App\Http\Middleware\EnsurePhase2Admin::class)->group(function () {
            Route::resource('users', UserController::class);
            Route::resource('subjects', \App\Http\Controllers\SubjectController::class)->except('show');
            Route::patch('subjects/{subject}/toggle-status', [\App\Http\Controllers\SubjectController::class, 'toggleStatus'])->name('subjects.toggleStatus');
            Route::resource('education-levels', \App\Http\Controllers\EducationLevelController::class)->except('show');
            Route::patch('education-levels/{education_level}/toggle-status', [\App\Http\Controllers\EducationLevelController::class, 'toggleStatus'])->name('education-levels.toggleStatus');
            Route::get('administrative-areas', [\App\Http\Controllers\AdministrativeAreaController::class, 'index'])->name('administrative-areas.index');
            Route::resource('banners', BannerController::class)->except('show');
            Route::patch('banners/{banner}/toggle-status', [BannerController::class, 'toggleStatus'])->name('banners.toggleStatus');
            Route::prefix('verifications')->name('verifications.')->controller(\App\Http\Controllers\VerificationController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/teachers', 'teachers')->name('teachers');
                Route::get('/centers', 'centers')->name('centers');
                Route::get('/documents/{document}', 'document')->name('document');
                Route::get('/{verification}', 'show')->name('show');
                Route::post('/{verification}/approve', 'approve')->name('approve');
                Route::post('/{verification}/reject', 'reject')->name('reject');
            });
        });
        Route::resource('categories', CategoryController::class);
        Route::post('categories/ajax-store', [CategoryController::class, 'ajaxStore'])->name('categories.ajaxStore');
        Route::resource('articles', ArticleController::class);
        Route::post('uploadCKEditor', [UploadController::class, 'uploadCKEditor'])->name('uploadCKEditor');
        Route::get('search', [\App\Http\Controllers\SearchController::class, 'index'])->name('search');

        // Profile routes
        Route::get('profile', [\App\Http\Controllers\ProfileController::class, 'index'])->name('profile.index');
        Route::put('profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    });
});

Route::get('/storage/avatars/{filename}', \App\Http\Controllers\Api\PublicAvatarController::class)
    ->where('filename', '[A-Za-z0-9][A-Za-z0-9._-]*')
    ->name('storage.avatars');

Route::get('/storage/covers/{filename}', \App\Http\Controllers\Api\PublicCoverController::class)
    ->where('filename', '[A-Za-z0-9][A-Za-z0-9._-]*')
    ->name('storage.covers');

Route::get('/login', function () {
    return view('login');
})->name('login.index');
Route::get('/signup', [LoginController::class, 'showSignupForm'])->name('signup.form');
Route::post('/signup', [LoginController::class, 'signup'])->name('signup');

Route::post('/login', [LoginController::class, 'login'])->name('login');
