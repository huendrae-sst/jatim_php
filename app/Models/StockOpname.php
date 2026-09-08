<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockOpname extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'period_year' => 'integer',
        'period_month' => 'integer',
        'opname_date' => 'date',
        'total_items' => 'integer',
        'discrepancy_items_count' => 'integer',
        'net_variance_qty' => 'integer',
        'net_variance_value' => 'float',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    public function getMonthNameAttribute(): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $months[$this->period_month] ?? "Bulan {$this->period_month}";
    }

    public function getPeriodFormattedAttribute(): string
    {
        return "{$this->month_name} {$this->period_year}";
    }
}
