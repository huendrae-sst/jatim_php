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

    public function getBudgetStatusAttribute(): string
    {
        $rate = $this->utilization_percentage;
        if ($rate >= 100) {
            return 'OVERBUDGET_BLOCK';
        } elseif ($rate >= 90) {
            return 'CRITICAL_90';
        } elseif ($rate >= 80) {
            return 'WARNING_80';
        }

        return 'SAFE';
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->budget_status) {
            'OVERBUDGET_BLOCK' => '<span class="badge bg-danger"><i class="bi bi-slash-circle me-1"></i>Overbudget &ge;100%</span>',
            'CRITICAL_90' => '<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle-fill me-1"></i>Kritis &ge;90%</span>',
            'WARNING_80' => '<span class="badge bg-info text-dark"><i class="bi bi-exclamation-circle me-1"></i>Siaga &ge;80%</span>',
            default => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Aman &lt;80%</span>',
        };
    }
}
