<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwitchingStockItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'qty_requested' => 'integer',
    ];

    public function switchingStock(): BelongsTo
    {
        return $this->belongsTo(SwitchingStock::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
