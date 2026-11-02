<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdministrativeArea;
use App\Models\Banner;
use App\Models\EducationLevel;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;

class MasterDataController extends Controller
{
    public function subjects()
    {
        $items = Subject::where(fn ($q) => $q->where('is_active', true)->orWhereHas('posts', fn ($p) => $p->where('moderation_status', 'approved')))
            ->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'slug', 'icon_path', 'sort_order']);

        return response()->json(['data' => $items->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'slug' => $s->slug,
            'icon_url' => $s->icon_path ? url('/storage/'.$s->icon_path) : null, 'sort_order' => $s->sort_order])]);
    }

    public function levels()
    {
        return response()->json(['data' => EducationLevel::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'code', 'name', 'sort_order'])]);
    }

    public function banners()
    {
        $items = Banner::where('status', 'active')->whereNotNull('image_path')
            ->where(fn ($q) => $q->whereNull('position')->orWhereIn('position', ['1', 'home']))
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('sort_order')->orderBy('id')->get();

        return response()->json(['data' => $items->map(fn ($b) => ['id' => $b->id, 'title' => $b->title,
            'image_url' => url('/storage/'.$b->image_path), 'link_url' => $b->link_url, 'sort_order' => $b->sort_order])]);
    }

    public function provinces()
    {
        return response()->json(['data' => AdministrativeArea::where('is_active', true)->where('level', 'province')->orderBy('name')->get(['id', 'code', 'name', 'level', 'parent_id'])]);
    }

    public function districts(AdministrativeArea $province)
    {
        abort_unless($province->is_active && $province->level === 'province', 404);

        return $this->children($province, 'district');
    }

    public function wards(AdministrativeArea $parent)
    {
        abort_unless($parent->is_active && in_array($parent->level, ['province', 'district'], true), 404);

        return $this->children($parent, 'ward');
    }

    private function children(AdministrativeArea $parent, string $level)
    {
        return response()->json(['data' => AdministrativeArea::where('is_active', true)->where('parent_id', $parent->id)->where('level', $level)->orderBy('name')->get(['id', 'code', 'name', 'level', 'parent_id'])]);
    }

    public function verificationStatuses(Request $request)
    {
        $data = $request->validate(['user_ids' => ['required', 'array', 'max:100'], 'user_ids.*' => ['required', 'integer', 'min:1']]);
        $users = User::with(['teacherProfile:user_id,verification_status,verified_at', 'center:id,owner_user_id,verification_status,verified_at'])
            ->where('status', 'active')->whereIn('role', ['teacher', 'center'])->whereIn('id', $data['user_ids'])->get(['id', 'role']);

        return response()->json(['data' => $users->map(function ($user) {
            $profile = $user->role === 'teacher' ? $user->teacherProfile : $user->center;

            return ['user_id' => $user->id, 'role' => $user->role, 'verification_status' => $profile?->verification_status ?? 'unverified', 'verified_at' => $profile?->verified_at];
        })]);
    }

    public function provider(User $user)
    {
        abort_unless($user->status === 'active' && in_array($user->role, ['teacher', 'center'], true), 404);
        $profile = $user->role === 'teacher' ? $user->teacherProfile : $user->center;
        abort_unless($profile, 404);

        return response()->json(['data' => [
            'id' => $user->id, 'role' => $user->role, 'name' => $user->role === 'center' ? $profile->name : $user->name,
            'avatar' => $user->avatar_url, 'bio' => $profile->bio, 'qualifications' => $profile->headline ?? '',
            'teachingField' => $profile->teaching_field ?? '',
            'experience' => $profile->experience ?: (isset($profile->experience_years) ? $profile->experience_years.' năm kinh nghiệm' : ''),
            'foundedYear' => $profile->founded_year ?? '', 'verification_status' => $profile->verification_status,
            'verified_at' => $profile->verified_at, 'isPhonePublic' => $user->role === 'center' || $profile->show_phone,
            'phone' => $user->role === 'center' ? $profile->hotline : ($profile->show_phone ? $user->phone : null),
        ]]);
    }
}
