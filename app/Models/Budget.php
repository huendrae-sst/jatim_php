<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'committed_amount' => 'decimal:2',
        'realized_amount' => 'decimal:2',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function getAvailableAmountAttribute(): float
    {
        return max(0, (float) ($this->allocated_amount - $this->committed_amount - $this->realized_amount));
    }

    public function getUtilizationPercentageAttribute(): float
    {
        if ($this->allocated_amount <= 0) {
            return 0;
        }

        return round((($this->committed_amount + $this->realized_amount) / $this->allocated_amount) * 100, 2);
    }
}
