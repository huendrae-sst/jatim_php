<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmbossRecord extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'qty' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function embossFile(): BelongsTo
    {
        return $this->belongsTo(EmbossFile::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Masked Card Number / PAN for security compliance (POC-54).
     * Example: 6013-02**-****-1234
     */
    public function getMaskedCardNumberAttribute(): string
    {
        if (! $this->card_number) {
            return '-';
        }

        $clean = preg_replace('/[^0-9]/', '', $this->card_number);
        $len = strlen($clean);

        if ($len < 8) {
            return str_repeat('*', $len);
        }

        $first6 = substr($clean, 0, 6);
        $last4 = substr($clean, -4);
        $maskedCount = max(0, $len - 10);

        // Format as 6013-02**-****-1234
        if ($len === 16) {
            return substr($clean, 0, 4).'-'.substr($clean, 4, 2).'**-****-'.$last4;
        }

        return $first6.str_repeat('*', $maskedCount).$last4;
    }

    /**
     * Masked Cardholder Name for security compliance (POC-54).
     */
    public function getMaskedCardholderNameAttribute(): string
    {
        if (! $this->cardholder_name) {
            return '-';
        }

        $parts = explode(' ', trim($this->cardholder_name));
        if (count($parts) === 1) {
            $name = $parts[0];
            $visible = min(3, strlen($name));

            return substr($name, 0, $visible).str_repeat('*', max(1, strlen($name) - $visible));
        }

        $masked = [];
        foreach ($parts as $idx => $part) {
            if ($idx === 0) {
                $masked[] = $part;
            } else {
                $masked[] = substr($part, 0, 1).str_repeat('*', max(1, strlen($part) - 1));
            }
        }

        return implode(' ', $masked);
    }
}
