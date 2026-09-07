<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehousePacking extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'koli_count' => 'integer',
        'total_weight_kg' => 'decimal:2',
        'packed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function packer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'packed_by_user_id');
    }
}
