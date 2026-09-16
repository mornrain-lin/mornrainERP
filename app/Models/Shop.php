<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform_id', 'name', 'seller_account', 'region', 'currency',
        'exchange_rate', 'is_active', 'remark',
    ];

    protected $casts = [
        'exchange_rate' => 'float',
        'is_active' => 'bool',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** 店铺简称，列表页展示用：Shopee-MY-晨雨官方店 */
    public function getDisplayNameAttribute(): string
    {
        $platform = $this->platform?->name ?? '—';
        $region = $this->region ? '-' . $this->region : '';

        return "{$platform}{$region} · {$this->name}";
    }
}
