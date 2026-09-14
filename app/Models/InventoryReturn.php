<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryReturn extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'shipped_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(InventoryReturnItem::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function originWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'origin_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function getTotalQtyAttribute(): int
    {
        return (int) $this->items->sum('qty_returned');
    }

    public function getTotalValueAttribute(): float
    {
        return (float) $this->items->sum('subtotal');
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'REQUESTED' => '<span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Menunggu Persetujuan</span>',
            'APPROVED' => '<span class="badge bg-info text-dark"><i class="bi bi-check2 me-1"></i>Disetujui (Siap Kirim)</span>',
            'REJECTED' => '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Ditolak</span>',
            'SHIPPED' => '<span class="badge bg-primary"><i class="bi bi-truck me-1"></i>Dalam Pengiriman</span>',
            'RECEIVED' => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Diterima Selesai</span>',
            'CANCELLED' => '<span class="badge bg-secondary"><i class="bi bi-slash-circle me-1"></i>Dibatalkan</span>',
            default => '<span class="badge bg-secondary">'.$this->status.'</span>',
        };
    }
}
