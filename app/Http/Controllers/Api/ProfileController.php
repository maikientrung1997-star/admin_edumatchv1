<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    private const RELATIONS = [
        'studentProfile.province', 'studentProfile.district', 'studentProfile.ward',
        'teacherProfile.province', 'teacherProfile.district', 'teacherProfile.ward',
        'center.province', 'center.district', 'center.ward',
    ];

    public function show(Request $request)
    {
        return response()->json(['user' => $request->user()->load(self::RELATIONS)]);
    }

    public function update(UpdateProfileRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();
        DB::transaction(function () use ($user, $data) {
            if (array_key_exists('name', $data)) {
                // User names remain compact, while a center's public name can
                // use the full 200-character allowance on its own profile.
                $user->update(['name' => mb_substr($data['name'], 0, 150)]);
            }
            $profileData = $data;
            unset($profileData['name']);

            if ($user->role === 'teacher') {
                $user->teacherProfile()->updateOrCreate(
                    ['user_id' => $user->id],
                    $profileData + ['full_name' => $user->name],
                );
            } elseif ($user->role === 'center') {
                $centerData = \Illuminate\Support\Arr::only($profileData, ['bio', 'teaching_field', 'founded_year']);
                if (array_key_exists('name', $data)) {
                    $centerData['name'] = $data['name'];
                }
                $user->center()->updateOrCreate(
                    ['owner_user_id' => $user->id],
                    $centerData + ['name' => $user->name],
                );
            } else {
                $user->studentProfile()->updateOrCreate(['user_id' => $user->id], $profileData + ['full_name' => $user->name]);
            }
        });

        return response()->json(['message' => 'Đã cập nhật hồ sơ cá nhân.', 'user' => $user->fresh()->load(self::RELATIONS)]);
    }

    public function avatar(Request $request)
    {
        $request->validate(['avatar' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048']]);
        $path = $request->file('avatar')->store('avatars', 'public');
        abort_unless($path, 500, 'Không thể lưu ảnh đại diện.');
        $user = $request->user();
        $oldPath = $user->avatar_path;
        try {
            $user->update(['avatar_path' => $path]);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }
        if (is_string($oldPath) && str_starts_with($oldPath, 'avatars/')) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json([
            'message' => 'Đã cập nhật ảnh đại diện.', 'avatar_url' => $user->avatar_url,
            'user' => $user->load(self::RELATIONS),
        ]);
    }

    public function cover(Request $request)
    {
        $request->validate([
            'cover_image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp'],
        ]);

        $path = $request->file('cover_image')->store('covers', 'public');
        abort_unless($path, 500, 'Không thể lưu ảnh bìa.');

        $user = $request->user();
        $oldPath = $user->cover_image_path;

        try {
            $user->update(['cover_image_path' => $path]);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        if (is_string($oldPath) && str_starts_with($oldPath, 'covers/')) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json([
            'message' => 'Đã cập nhật ảnh bìa.',
            'cover_image_url' => $user->cover_image_url,
            'user' => $user->fresh()->load(self::RELATIONS),
        ]);
    }
}
