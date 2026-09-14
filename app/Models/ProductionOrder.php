<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOrder extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'issued_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ProductionOrderItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function destinationOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'destination_organization_id');
    }

    public function embossFile(): BelongsTo
    {
        return $this->belongsTo(EmbossFile::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function getTotalPlannedQtyAttribute(): int
    {
        return (int) $this->items->sum('qty_planned');
    }

    public function getTotalIssuedQtyAttribute(): int
    {
        return (int) $this->items->sum('qty_issued');
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'PLANNED' => '<span class="badge bg-secondary"><i class="bi bi-file-earmark me-1"></i>Direncanakan</span>',
            'ISSUED' => '<span class="badge bg-primary"><i class="bi bi-box-arrow-right me-1"></i>Bahan Dikeluarkan</span>',
            'IN_PRODUCTION' => '<span class="badge bg-warning text-dark"><i class="bi bi-gear-wide-connected me-1"></i>Dalam Proses Perso</span>',
            'COMPLETED' => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Selesai Produksi</span>',
            'CANCELLED' => '<span class="badge bg-danger"><i class="bi bi-slash-circle me-1"></i>Dibatalkan</span>',
            default => '<span class="badge bg-secondary">'.$this->status.'</span>',
        };
    }
}
