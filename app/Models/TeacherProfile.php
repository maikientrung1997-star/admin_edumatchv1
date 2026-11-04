<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherProfile extends Model
{
    use HasFactory;

    protected $table = 'teacher_profiles';

    protected $fillable = [
        'user_id',
        'full_name',
        'date_of_birth',
        'headline',
        'teaching_field',
        'bio',
        'experience',
        'experience_years',
        'province_id',
        'district_id',
        'ward_id',
        'show_phone',
        'verification_status',
        'verified_at',
        'rating_avg',
        'review_count',
        'trust_count',
    ];

    protected $attributes = [
        'experience_years' => 0,
        'show_phone' => false,
        'verification_status' => 'unverified',
        'rating_avg' => 0,
        'review_count' => 0,
        'trust_count' => 0,
    ];

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'date_of_birth' => 'date:Y-m-d',
        'experience_years' => 'integer',
        'province_id' => 'integer',
        'district_id' => 'integer',
        'ward_id' => 'integer',
        'show_phone' => 'boolean',
        'verified_at' => 'datetime',
        'rating_avg' => 'decimal:2',
        'review_count' => 'integer',
        'trust_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(AdministrativeArea::class, 'province_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(AdministrativeArea::class, 'district_id');
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(AdministrativeArea::class, 'ward_id');
    }
}
