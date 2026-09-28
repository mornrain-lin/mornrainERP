<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    protected $fillable = [
        'shop_id', 'trigger', 'status', 'range_from', 'range_to',
        'fetched', 'created', 'updated', 'skipped', 'duration_ms', 'message',
    ];

    protected $casts = [
        'range_from' => 'datetime',
        'range_to' => 'datetime',
        'fetched' => 'int',
        'created' => 'int',
        'updated' => 'int',
        'skipped' => 'int',
        'duration_ms' => 'int',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function summary(): string
    {
        return sprintf(
            '拉取 %d / 新建 %d / 更新 %d / 跳过 %d',
            $this->fetched, $this->created, $this->updated, $this->skipped
        );
    }
}
