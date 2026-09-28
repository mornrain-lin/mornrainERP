<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'carrier', 'tracking_no', 'status', 'cost', 'shipped_at', 'delivered_at',
        'events', 'last_tracked_at',
    ];

    protected $casts = [
        'status' => ShipmentStatus::class,
        'cost' => 'float',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'last_tracked_at' => 'datetime',
        'events' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** 物流轨迹时间线（倒序展示用） */
    public function timeline(): array
    {
        return array_reverse((array) ($this->events ?? []));
    }

    /** 常见跨境物流商，供下拉框 */
    public const CARRIERS = [
        '云途物流', '燕文物流', '4PX 递四方', '顺丰国际', '极兔国际',
        '中国邮政小包', 'DHL eCommerce', 'FedEx', 'YunExpress', '其他',
    ];
}
