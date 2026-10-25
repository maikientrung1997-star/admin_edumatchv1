<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'role',
        'name',
        'email',
        'google_id',
        'phone',
        'password',
        'avatar_path',
        'status',
        'email_verified_at',
        'phone_verified_at',
        'last_login_at',
        'blocked_reason',
        'blocked_at',
        'full_name',
        'avatar',
        'zalo_id',
        'gender',
        'birthday',
        'address',
        'balance',
        'referrer_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
    ];

    protected $appends = ['avatar_url'];

    public function getAvatarUrlAttribute(): ?string
    {
        $path = $this->avatar_path;
        if (! $path) {
            return null;
        }
        if (preg_match('#^https?://#', $path)) {
            return $path;
        }

        return url('/storage/'.preg_replace('#^/?storage/#', '', $path));
    }

    protected $attributes = [
        'role' => 'student',
        'status' => 'active',
    ];

    protected $casts = [
        'id' => 'integer',
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'blocked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'password' => 'hashed',
        'birthday' => 'date',
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            // Existing registration and seeders still submit full_name.
            if ($user->name === null && $user->full_name !== null) {
                $user->name = mb_substr($user->full_name, 0, 150);
            }
        });
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class, 'user_id');
    }

    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class, 'user_id');
    }

    public function center(): HasOne
    {
        return $this->hasOne(Center::class, 'owner_user_id');
    }

    public function teacherSubjects(): HasMany
    {
        return $this->hasMany(TeacherSubject::class, 'teacher_user_id');
    }

    public function studentLearningNeeds(): HasMany
    {
        return $this->hasMany(StudentLearningNeed::class, 'student_user_id');
    }

    public function teacherQualifications(): HasMany
    {
        return $this->hasMany(TeacherQualification::class, 'teacher_user_id');
    }

    public function teacherVerificationRequests(): HasMany
    {
        return $this->hasMany(VerificationRequest::class, 'teacher_user_id');
    }

    public function submittedVerificationRequests(): HasMany
    {
        return $this->hasMany(VerificationRequest::class, 'submitted_by_user_id');
    }

    public function reviewedVerificationRequests(): HasMany
    {
        return $this->hasMany(VerificationRequest::class, 'reviewed_by');
    }

    public function reviewedVerificationDocuments(): HasMany
    {
        return $this->hasMany(VerificationDocument::class, 'reviewed_by');
    }

    public function teachingPosts(): HasMany
    {
        return $this->hasMany(Post::class, 'teacher_user_id');
    }

    public function teachingBatches(): HasMany
    {
        return $this->hasMany(CourseBatch::class, 'teacher_user_id');
    }

    public function postModerationLogs(): HasMany
    {
        return $this->hasMany(PostModerationLog::class, 'action_by_user_id');
    }

    public function consultationRequests(): HasMany
    {
        return $this->hasMany(ConsultationRequest::class, 'student_user_id');
    }

    public function requestStatusLogs(): HasMany
    {
        return $this->hasMany(RequestStatusLog::class, 'changed_by_user_id');
    }

    public function postFavorites(): HasMany
    {
        return $this->hasMany(PostFavorite::class, 'student_user_id');
    }

    public function profileInteractions(): HasMany
    {
        return $this->hasMany(ProfileInteraction::class, 'student_user_id');
    }

    public function receivedProfileInteractions(): HasMany
    {
        return $this->hasMany(ProfileInteraction::class, 'teacher_user_id');
    }

    public function writtenReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'student_user_id');
    }

    public function receivedReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'teacher_user_id');
    }

    public function moderatedReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'moderated_by');
    }

    public function feedPosts(): HasMany
    {
        return $this->hasMany(FeedPost::class, 'author_user_id');
    }

    public function feedLikes(): HasMany
    {
        return $this->hasMany(FeedLike::class, 'user_id');
    }

    public function feedComments(): HasMany
    {
        return $this->hasMany(FeedComment::class, 'user_id');
    }

    public function studentConversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class, 'student_user_id');
    }

    public function teacherConversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class, 'teacher_user_id');
    }

    public function chatParticipants(): HasMany
    {
        return $this->hasMany(ChatParticipant::class, 'user_id');
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'sender_user_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    public function submittedReports(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_user_id');
    }

    public function handledReports(): HasMany
    {
        return $this->hasMany(Report::class, 'handled_by');
    }

    public function homepageFeatures(): HasMany
    {
        return $this->hasMany(HomepageFeature::class, 'teacher_user_id');
    }

    public function adminLogs(): HasMany
    {
        return $this->hasMany(AdminLog::class, 'admin_user_id');
    }

    public function userActivityLogs(): HasMany
    {
        return $this->hasMany(UserActivityLog::class, 'user_id');
    }

    public function authVerificationTokens(): HasMany
    {
        return $this->hasMany(AuthVerificationToken::class, 'user_id');
    }

    public function searchLogs(): HasMany
    {
        return $this->hasMany(SearchLog::class, 'user_id');
    }

    public function updatedSystemSettings(): HasMany
    {
        return $this->hasMany(SystemSetting::class, 'updated_by');
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'teacher_subjects', 'teacher_user_id', 'subject_id')
            ->withPivot(['id', 'level_note'])->withTimestamps();
    }

    public function favoritePosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_favorites', 'student_user_id', 'post_id')
            ->withPivot(['id'])->withTimestamps();
    }

    public function likedFeedPosts(): BelongsToMany
    {
        return $this->belongsToMany(FeedPost::class, 'feed_likes', 'user_id', 'feed_post_id')
            ->withPivot(['id'])->withTimestamps();
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(ChatConversation::class, 'chat_participants', 'user_id', 'conversation_id')
            ->withPivot(['id', 'last_read_at', 'is_muted', 'joined_at'])->withTimestamps();
    }
}
