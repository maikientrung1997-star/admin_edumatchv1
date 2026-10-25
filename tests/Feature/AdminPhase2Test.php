<?php

namespace Tests\Feature;

use App\Events\NotificationCreated;
use App\Models\AdministrativeArea;
use App\Models\Banner;
use App\Models\EducationLevel;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Subject;
use App\Models\TeacherSubject;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Models\VerificationRequest;
use Database\Seeders\Phase2MasterDataSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\IsolatedMysqlTestCase;

class AdminPhase2Test extends IsolatedMysqlTestCase
{
    private function account(string $role = 'teacher'): User
    {
        $user = User::create(['name' => 'Test '.$role, 'email' => uniqid().'@example.test', 'password' => 'Password@123', 'role' => $role]);
        if ($role === 'teacher') {
            $user->teacherProfile()->create(['full_name' => $user->name, 'date_of_birth' => '1990-05-15']);
        }
        if ($role === 'center') {
            $user->center()->create(['name' => 'Trung tâm thử nghiệm', 'slug' => 'center-'.$user->id, 'founded_year' => 2020]);
        }

        return $user;
    }

    private function admin(): User
    {
        $user = $this->account('super_admin');
        $user->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->actingAs($user, 'web');

        return $user;
    }

    private function docs(string $role = 'teacher'): array
    {
        $types = $role === 'center' ? ['business_registration'] : ['degree', 'identity_front', 'identity_back'];

        return array_map(fn ($type) => ['document_type' => $type, 'document_name' => 'Tài liệu '.$type, 'issuing_authority' => 'Đơn vị cấp', 'issued_year' => ($role === 'center' ? null : 2020), 'file' => UploadedFile::fake()->image($type.'.jpg')], $types);
    }

    private function api(User $user, string $method, string $path, array $data = [])
    {
        $this->app['auth']->forgetGuards();
        $token = $user->createToken('test')->plainTextToken;

        return $this->json($method, $path, $data, ['Authorization' => 'Bearer '.$token]);
    }

    private function submission(User $user): VerificationRequest
    {
        Storage::fake('verification');
        $data = ['documents' => $this->docs($user->role)];
        if ($user->role === 'center') {
            $data['center_details'] = ['center_name' => 'Trung tâm thử nghiệm', 'training_field' => 'Ngoại ngữ', 'training_address' => 'Địa chỉ đào tạo QA',
                'company_name' => 'Công ty QA', 'company_address' => 'Địa chỉ công ty QA', 'tax_code' => '0123456789',
                'representative_name' => 'Đại diện QA', 'representative_phone' => '0981234567'];
        }
        $id = $this->api($user, 'POST', '/api/verifications', $data)->assertCreated()->json('request.id');

        return VerificationRequest::findOrFail($id);
    }

    public function test_admin_can_view_and_crud_subjects(): void
    {
        Storage::fake('public');
        $this->admin();
        $this->get('/admin/subjects')->assertOk()->assertSee('Quản lý Môn học');
        $this->get('/admin/subjects/create')->assertOk();
        $this->post('/admin/subjects', ['name' => 'Toán nâng cao', 'icon' => UploadedFile::fake()->image('icon.png'), 'sort_order' => 3, 'is_active' => 1])->assertRedirect('/admin/subjects');
        $subject = Subject::firstOrFail();
        $this->assertSame('toan-nang-cao', $subject->slug);
        Storage::disk('public')->assertExists($subject->icon_path);
        $old = $subject->icon_path;
        $this->get('/admin/subjects/'.$subject->id.'/edit')->assertOk();
        $this->put('/admin/subjects/'.$subject->id, ['name' => 'Toán', 'slug' => 'toan', 'icon' => UploadedFile::fake()->image('new.png'), 'sort_order' => 1, 'is_active' => 1])->assertRedirect();
        Storage::disk('public')->assertMissing($old);
        $this->patchJson('/admin/subjects/'.$subject->id.'/toggle-status')->assertOk()->assertJsonPath('is_active', false);
        $path = $subject->fresh()->icon_path;
        $this->delete('/admin/subjects/'.$subject->id)->assertRedirect();
        $this->assertDatabaseCount('subjects', 0);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_subject_validation_rejects_duplicate_slug_and_unsafe_svg_and_referenced_delete(): void
    {
        Storage::fake('public');
        $this->admin();
        $subject = Subject::create(['name' => 'Toán', 'slug' => 'toan']);
        $this->postJson('/admin/subjects', ['name' => 'Môn khác', 'slug' => 'toan', 'sort_order' => 0, 'is_active' => 1])->assertUnprocessable()->assertJsonValidationErrors('slug');
        $svg = UploadedFile::fake()->createWithContent('unsafe.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->postJson('/admin/subjects', ['name' => 'Môn mới', 'sort_order' => 0, 'is_active' => 1, 'icon' => $svg])->assertUnprocessable()->assertJsonValidationErrors('icon');
        TeacherSubject::create(['teacher_user_id' => $this->account()->id, 'subject_id' => $subject->id]);
        $this->deleteJson('/admin/subjects/'.$subject->id)->assertUnprocessable();
        $this->assertDatabaseHas('subjects', ['id' => $subject->id]);
    }

    public function test_safe_svg_upload_is_supported(): void
    {
        Storage::fake('public');
        $this->admin();
        $svg = UploadedFile::fake()->createWithContent('safe.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="#123456"/></svg>');
        $this->postJson('/admin/subjects', ['name' => 'SVG', 'sort_order' => 0, 'is_active' => 1, 'icon' => $svg])->assertRedirect();
        Storage::disk('public')->assertExists(Subject::firstOrFail()->icon_path);
    }

    public function test_admin_can_view_and_crud_education_levels(): void
    {
        $this->admin();
        $this->get('/admin/education-levels')->assertOk();
        $this->get('/admin/education-levels/create')->assertOk();
        $this->post('/admin/education-levels', ['code' => 'grade-1', 'name' => 'Lớp 1', 'sort_order' => 1, 'is_active' => 1])->assertRedirect();
        $level = EducationLevel::firstOrFail();
        $this->get('/admin/education-levels/'.$level->id.'/edit')->assertOk();
        $this->put('/admin/education-levels/'.$level->id, ['code' => 'grade-2', 'name' => 'Lớp 2', 'sort_order' => 2, 'is_active' => 1])->assertRedirect();
        $this->patchJson('/admin/education-levels/'.$level->id.'/toggle-status')->assertOk()->assertJsonPath('is_active', false);
        $this->postJson('/admin/education-levels', ['code' => 'INVALID CODE', 'name' => 'X', 'sort_order' => 0, 'is_active' => 1])->assertUnprocessable();
        $this->delete('/admin/education-levels/'.$level->id)->assertRedirect();
        $this->assertDatabaseCount('education_levels', 0);
    }

    public function test_admin_can_upload_replace_sort_and_delete_banner(): void
    {
        Storage::fake('public');
        $this->admin();
        $data = ['title' => 'Banner thử', 'image' => UploadedFile::fake()->image('banner.png'), 'link' => 'https://example.com', 'position' => 'home', 'sort_order' => 4, 'status' => 1];
        $this->post('/admin/banners', $data)->assertRedirect();
        $banner = Banner::firstOrFail();
        $old = $banner->image_path;
        $this->get('/admin/banners')->assertOk()->assertSee('Banner thử');
        $this->get('/admin/banners/'.$banner->id.'/edit')->assertOk();
        $data['image'] = UploadedFile::fake()->image('replacement.png');
        $this->put('/admin/banners/'.$banner->id, $data)->assertRedirect();
        Storage::disk('public')->assertMissing($old);
        $this->patch('/admin/banners/'.$banner->id.'/toggle-status')->assertRedirect();
        $this->assertSame('inactive', $banner->fresh()->status);
        $this->delete('/admin/banners/'.$banner->id)->assertRedirect();
        Storage::disk('public')->assertMissing($banner->fresh()?->image_path ?? $banner->image_path);
    }

    public function test_banner_rejects_unsafe_url_and_invalid_schedule(): void
    {
        $this->admin();
        $this->postJson('/admin/banners', ['title' => 'Banner', 'image' => UploadedFile::fake()->image('x.png'), 'link' => 'javascript:alert(1)', 'sort_order' => 0, 'status' => 1, 'start_date' => '2026-10-19', 'end_date' => '2026-10-18'])
            ->assertUnprocessable()->assertJsonValidationErrors(['link', 'end_date']);
    }

    public function test_non_admin_cannot_access_verification_center_or_modify_catalogs(): void
    {
        foreach (['teacher', 'student', 'center'] as $role) {
            $this->actingAs($this->account($role), 'web');
            foreach (['/admin/subjects', '/admin/education-levels', '/admin/banners', '/admin/verifications', '/admin/administrative-areas'] as $path) {
                $this->get($path)->assertForbidden();
            }
            $this->post('/admin/subjects', ['name' => 'Forbidden'])->assertForbidden();
        }
    }

    public function test_admin_can_approve_teacher_verification_request(): void
    {
        $teacher = $this->account();
        $record = $this->submission($teacher);
        $admin = $this->admin();
        $this->get('/admin/verifications')->assertOk()->assertSee($teacher->name);
        $this->get('/admin/verifications/teachers')->assertOk();
        $this->get('/admin/verifications/'.$record->id)->assertOk()->assertSee('Tài liệu degree');
        $this->post('/admin/verifications/'.$record->id.'/approve')->assertRedirect();
        $this->assertSame('approved', $record->fresh()->status);
        $this->assertSame($admin->id, $record->fresh()->reviewed_by);
        $this->assertSame('verified', $teacher->teacherProfile->fresh()->verification_status);
        $this->assertNotNull($teacher->teacherProfile->fresh()->verified_at);
        $this->assertSame(3, $record->verificationDocuments()->where('status', 'approved')->count());
        $this->assertDatabaseHas('notifications', ['user_id' => $teacher->id, 'type' => 'verification_result']);
        $this->post('/admin/verifications/'.$record->id.'/approve')->assertStatus(409);
        $this->assertDatabaseCount('notifications', 1);
        $this->getJson('/api/provider-verifications?user_ids[]='.$teacher->id)->assertOk()->assertJsonPath('data.0.verification_status', 'verified');
    }

    public function test_admin_can_approve_center_verification_request(): void
    {
        $center = $this->account('center');
        $record = $this->submission($center);
        $this->admin();
        $this->get('/admin/verifications/centers')->assertOk();
        $this->post('/admin/verifications/'.$record->id.'/approve')->assertRedirect();
        $this->assertSame('verified', $center->center->fresh()->verification_status);
        $this->assertNotNull($center->center->fresh()->verified_at);
    }

    public function test_admin_reject_requires_reason_and_updates_status_and_allows_resubmission(): void
    {
        $teacher = $this->account();
        $record = $this->submission($teacher);
        $this->admin();
        $this->postJson('/admin/verifications/'.$record->id.'/reject', [])->assertUnprocessable()->assertJsonValidationErrors('rejection_reason');
        $this->post('/admin/verifications/'.$record->id.'/reject', ['rejection_reason' => 'Ảnh bị mờ'])->assertRedirect();
        $this->assertSame('rejected', $teacher->teacherProfile->fresh()->verification_status);
        $this->assertNull($teacher->teacherProfile->fresh()->verified_at);
        $this->assertSame('Ảnh bị mờ', $record->fresh()->rejection_reason);
        $this->api($teacher, 'GET', '/api/verifications')->assertOk()->assertJsonPath('requests.data.0.rejection_reason', 'Ảnh bị mờ');
        $this->api($teacher, 'POST', '/api/verifications', ['documents' => $this->docs()])->assertCreated();
        $this->assertSame(2, VerificationRequest::count());
        $this->assertSame('pending', $teacher->teacherProfile->fresh()->verification_status);
    }

    public function test_submission_uses_authenticated_owner_keeps_files_private_and_rejects_duplicates(): void
    {
        Storage::fake('verification');
        Storage::fake('public');
        $teacher = $this->account();
        $other = $this->account();
        $response = $this->api($teacher, 'POST', '/api/verifications', ['teacher_user_id' => $other->id, 'status' => 'approved', 'documents' => $this->docs()])->assertCreated()
            ->assertJsonPath('request.status', 'pending')->assertJsonMissingPath('request.documents.0.file_path');
        $record = VerificationRequest::findOrFail($response->json('request.id'));
        $doc = $record->verificationDocuments()->first();
        $this->assertSame($teacher->id, $record->teacher_user_id);
        Storage::disk('verification')->assertExists($doc->file_path);
        Storage::disk('public')->assertMissing($doc->file_path);
        $this->api($teacher, 'POST', '/api/verifications', ['documents' => $this->docs()])->assertUnprocessable();
        $this->assertDatabaseCount('verification_requests', 1);
        $this->api($other, 'GET', '/api/verifications')->assertJsonCount(0, 'requests.data');
        $this->api($other, 'GET', '/api/verifications/documents/'.$doc->id)->assertForbidden();
        $this->api($teacher, 'GET', '/api/verifications/documents/'.$doc->id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->admin();
        $this->get('/admin/verifications/documents/'.$doc->id)->assertOk();
    }

    public function test_submission_validates_required_proof_file_type_metadata_and_role(): void
    {
        Storage::fake('verification');
        $teacher = $this->account();
        $docs = $this->docs();
        $docs[0]['file'] = UploadedFile::fake()->createWithContent('attack.html', '<script>alert(1)</script>');
        $docs[0]['issued_year'] = now()->year + 1;
        $this->api($teacher, 'POST', '/api/verifications', ['documents' => $docs])->assertUnprocessable()->assertJsonValidationErrors(['documents.0.file', 'documents.0.issued_year']);
        $this->api($teacher, 'POST', '/api/verifications', ['documents' => array_slice($this->docs(), 0, 1)])->assertUnprocessable();
        $this->api($this->account('center'), 'POST', '/api/verifications', ['documents' => $this->docs()])->assertUnprocessable();
        $this->api($this->account('student'), 'POST', '/api/verifications', ['documents' => $this->docs()])->assertForbidden();
        $this->assertDatabaseCount('verification_requests', 0);
        $this->assertSame([], Storage::disk('verification')->allFiles());
    }

    public function test_submission_accepts_pdf_and_rolls_back_files_on_failure(): void
    {
        Storage::fake('verification');
        $teacher = $this->account();
        $docs = $this->docs();
        $docs[0]['file'] = UploadedFile::fake()->createWithContent('degree.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
        $this->api($teacher, 'POST', '/api/verifications', ['documents' => $docs])->assertCreated()->assertJsonPath('request.documents.0.mime_type', 'application/pdf');
        $other = $this->account();
        $before = Storage::disk('verification')->allFiles();
        VerificationDocument::creating(fn () => throw new \RuntimeException('Simulated failure'));
        try {
            $this->api($other, 'POST', '/api/verifications', ['documents' => $this->docs()])->assertStatus(500);
        } finally {
            VerificationDocument::flushEventListeners();
        }
        $this->assertSame($before, Storage::disk('verification')->allFiles());
        $this->assertSame('unverified', $other->teacherProfile->fresh()->verification_status);
        $this->assertDatabaseCount('verification_requests', 1);
    }

    public function test_notification_results_are_private_and_can_be_marked_read(): void
    {
        $teacher = $this->account();
        $other = $this->account();
        $record = $this->submission($teacher);
        $this->admin();
        $this->post('/admin/verifications/'.$record->id.'/approve')->assertRedirect();
        $notification = Notification::firstOrFail();
        $this->api($other, 'GET', '/api/notifications')->assertJsonCount(0, 'data');
        $this->api($other, 'POST', '/api/notifications/'.$notification->id.'/read')->assertForbidden();
        $this->api($teacher, 'GET', '/api/notifications')->assertJsonPath('data.0.isRead', false);
        $this->api($teacher, 'POST', '/api/notifications/'.$notification->id.'/read')->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_public_master_data_filters_visibility_schedule_and_hierarchy(): void
    {
        $active = Subject::create(['name' => 'Môn đang bật', 'slug' => 'active', 'sort_order' => 2]);
        $hidden = Subject::create(['name' => 'Môn đã có bài', 'slug' => 'posted', 'is_active' => false, 'sort_order' => 1]);
        Subject::create(['name' => 'Ẩn', 'slug' => 'hidden', 'is_active' => false]);
        Post::create(['teacher_user_id' => $this->account()->id, 'subject_id' => $hidden->id, 'title' => 'Khóa học', 'slug' => 'course', 'description' => 'Nội dung', 'teaching_mode' => 'online', 'moderation_status' => 'approved']);
        $this->getJson('/api/subjects')->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $hidden->id)->assertJsonPath('data.1.id', $active->id);
        EducationLevel::create(['code' => 'grade-1', 'name' => 'Lớp 1']);
        EducationLevel::create(['code' => 'hidden', 'name' => 'Ẩn', 'is_active' => false]);
        $this->getJson('/api/education-levels')->assertJsonCount(1, 'data');
        Banner::create(['title' => 'Hiện', 'image_path' => 'banners/test.png', 'position' => 'home']);
        Banner::create(['title' => 'Hết hạn', 'image_path' => 'banners/test.png', 'ends_at' => now()->subDay()]);
        Banner::create(['title' => 'Chưa tới', 'image_path' => 'banners/test.png', 'starts_at' => now()->addDay()]);
        $this->getJson('/api/banners')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Hiện');
        $province = AdministrativeArea::create(['code' => '01', 'name' => 'Tỉnh A', 'level' => 'province']);
        $district = AdministrativeArea::create(['code' => '001', 'name' => 'Huyện A', 'level' => 'district', 'parent_id' => $province->id]);
        AdministrativeArea::create(['code' => '0001', 'name' => 'Xã A', 'level' => 'ward', 'parent_id' => $province->id]);
        $this->getJson('/api/administrative-areas/provinces')->assertJsonCount(1, 'data');
        $this->getJson('/api/administrative-areas/districts/'.$province->id)->assertJsonPath('data.0.id', $district->id);
        $this->getJson('/api/administrative-areas/wards/'.$province->id)->assertJsonCount(1, 'data');
        $this->getJson('/api/administrative-areas/districts/'.$district->id)->assertNotFound();
        $this->admin();
        $this->get('/admin/administrative-areas?parent_id='.$province->id)->assertOk()->assertSee('Xã A');
    }

    public function test_provider_public_profile_does_not_expose_private_identity_or_phone(): void
    {
        $user = $this->account();
        $user->update(['phone' => '+84981234567']);
        $this->getJson('/api/providers/'.$user->id)->assertOk()->assertJsonPath('data.phone', null)->assertJsonMissingPath('data.email')->assertJsonMissingPath('data.date_of_birth');
        $this->getJson('/api/provider-verifications?user_ids[]='.$user->id)->assertJsonPath('data.0.verification_status', 'unverified')->assertJsonMissingPath('data.0.file_path');
        $user->update(['status' => 'blocked']);
        $this->getJson('/api/providers/'.$user->id)->assertNotFound();
    }

    public function test_master_data_seeder_is_repeatable_without_overwriting_admin_edits(): void
    {
        $this->seed(Phase2MasterDataSeeder::class);
        $subject = Subject::firstOrFail();
        $subject->update(['is_active' => false, 'slug' => 'custom-preserved']);
        $this->seed(Phase2MasterDataSeeder::class);
        $this->assertDatabaseCount('subjects', 13);
        $this->assertDatabaseCount('education_levels', 16);
        $this->assertFalse($subject->fresh()->is_active);
        $this->assertSame('custom-preserved', $subject->fresh()->slug);
    }

    public function test_subject_names_support_the_plan_limit_of_150_characters(): void
    {
        $this->admin();
        $this->post('/admin/subjects', ['name' => str_repeat('A', 150), 'sort_order' => 0, 'is_active' => 1])->assertRedirect();
        $this->assertSame(150, mb_strlen(Subject::firstOrFail()->name));
        $this->postJson('/admin/subjects', ['name' => str_repeat('B', 151), 'sort_order' => 0, 'is_active' => 1])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_verified_provider_cannot_resubmit_or_lose_an_existing_badge(): void
    {
        $teacher = $this->account();
        $record = $this->submission($teacher);
        $this->admin();
        $this->post('/admin/verifications/'.$record->id.'/approve')->assertRedirect();
        $this->api($teacher, 'POST', '/api/verifications', ['documents' => $this->docs()])->assertUnprocessable();
        $this->assertSame('verified', $teacher->teacherProfile->fresh()->verification_status);
        $this->assertDatabaseCount('verification_requests', 1);
        $this->assertCount(3, Storage::disk('verification')->allFiles());
    }

    public function test_admin_cannot_review_their_own_provider_verification(): void
    {
        $teacher = $this->account();
        $record = $this->submission($teacher);
        $teacher->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->actingAs($teacher, 'web');
        $this->post('/admin/verifications/'.$record->id.'/approve')->assertForbidden();
        $this->assertSame('pending', $record->fresh()->status);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_notification_event_uses_a_private_owner_channel_and_exposes_only_ui_fields(): void
    {
        $user = $this->account();
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'verification_result',
            'title' => 'Hồ sơ đã được duyệt',
            'content' => 'Bạn đã nhận huy hiệu.',
            'data' => ['link' => '/xac-minh'],
        ]);
        $event = new NotificationCreated($notification);

        $this->assertSame('notification.created', $event->broadcastAs());
        $this->assertSame('private-user.'.$user->id, $event->broadcastOn()[0]->name);
        $this->assertSame([
            'id' => 'server-'.$notification->id,
            'server_id' => $notification->id,
            'type' => 'verification_result',
            'title' => 'Hồ sơ đã được duyệt',
            'description' => 'Bạn đã nhận huy hiệu.',
            'link' => '/xac-minh',
            'isRead' => false,
            'createdAt' => $notification->created_at->toIso8601String(),
        ], $event->broadcastWith());

    }

    public function test_private_notification_channel_only_authenticates_its_owner(): void
    {
        // Tests use the null broadcaster globally so review tests never call
        // Pusher. Re-register the channel on a Pusher connection for this
        // authorization-only check (it generates a local HMAC, no network IO).
        config(['broadcasting.default' => 'pusher']);
        require base_path('routes/channels.php');
        $owner = $this->account();
        $other = $this->account();
        $payload = ['socket_id' => '1234.5678', 'channel_name' => 'private-user.'.$owner->id];
        $ownerToken = $owner->createToken('pusher-owner')->plainTextToken;
        $otherToken = $other->createToken('pusher-other')->plainTextToken;

        $this->json('GET', '/api/auth/me', [], ['Authorization' => 'Bearer '.$ownerToken])
            ->assertOk()
            ->assertJsonPath('user.id', $owner->id);
        $this->app['auth']->forgetGuards();
        $this->call('POST', '/broadcasting/auth', $payload, [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$ownerToken])
            ->assertOk()
            ->assertJsonStructure(['auth']);
        $this->app['auth']->forgetGuards();
        $this->call('POST', '/broadcasting/auth', $payload, [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$otherToken])
            ->assertForbidden();
    }
}
