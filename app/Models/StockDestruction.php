<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockDestruction extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'approved_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(StockDestructionItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by_user_id');
    }

    public function getTotalQtyAttribute(): int
    {
        return (int) $this->items->sum('qty');
    }

    public function getTotalLossValueAttribute(): float
    {
        return (float) $this->items->sum('total_loss_value');
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'REQUESTED' => '<span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Menunggu Otorisasi</span>',
            'APPROVED' => '<span class="badge bg-info text-dark"><i class="bi bi-check2 me-1"></i>Disetujui (Siap Dimusnahkan)</span>',
            'REJECTED' => '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Ditolak</span>',
            'EXECUTED' => '<span class="badge bg-dark"><i class="bi bi-fire me-1"></i>Telah Dimusnahkan</span>',
            'CANCELLED' => '<span class="badge bg-secondary"><i class="bi bi-slash-circle me-1"></i>Dibatalkan</span>',
            default => '<span class="badge bg-secondary">'.$this->status.'</span>',
        };
    }
}
