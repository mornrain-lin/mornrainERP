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
        'api_base', 'app_key', 'app_secret', 'sync_enabled',
    ];

    protected $casts = [
        'exchange_rate' => 'float',
        'is_active' => 'bool',
        'sync_enabled' => 'bool',
        // 平台凭证落库即密文，数据库被导出也无法直接读取
        'app_secret' => 'encrypted',
        'access_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    protected $hidden = ['app_secret', 'access_token'];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }

    /** 是否已完成对接配置（缺 api_base / app_key 无法发起请求） */
    public function isApiConfigured(): bool
    {
        return filled($this->api_base) && filled($this->app_key) && filled($this->app_secret);
    }

    /** 店铺简称，列表页展示用：Shopee-MY-晨雨官方店 */
    public function getDisplayNameAttribute(): string
    {
        $platform = $this->platform?->name ?? '—';
        $region = $this->region ? '-' . $this->region : '';

        return "{$platform}{$region} · {$this->name}";
    }
}
