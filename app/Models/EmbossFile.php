<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmbossFile extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'total_records' => 'integer',
        'success_records' => 'integer',
        'reject_records' => 'integer',
        'duplicate_records' => 'integer',
        'duration_seconds' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function records(): HasMany
    {
        return $this->hasMany(EmbossRecord::class);
    }

    public function validRecords(): HasMany
    {
        return $this->hasMany(EmbossRecord::class)->where('status', 'VALID');
    }

    public function rejectedRecords(): HasMany
    {
        return $this->hasMany(EmbossRecord::class)->where('status', 'INVALID');
    }

    public function duplicateRecords(): HasMany
    {
        return $this->hasMany(EmbossRecord::class)->where('status', 'DUPLICATE');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function getSuccessRateAttribute(): float
    {
        if ($this->total_records <= 0) {
            return 0.0;
        }

        return round(($this->success_records / $this->total_records) * 100, 1);
    }
}
