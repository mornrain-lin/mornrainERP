<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case Pending = 'pending';       // 待揽收
    case InTransit = 'in_transit';  // 运输中
    case Delivered = 'delivered';   // 已签收
    case Failed = 'failed';         // 异常

    public function label(): string
    {
        return match ($this) {
            self::Pending => '待揽收',
            self::InTransit => '运输中',
            self::Delivered => '已签收',
            self::Failed => '异常',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warn',
            self::InTransit => 'info',
            self::Delivered => 'success',
            self::Failed => 'danger',
        };
    }

    public static function options(): array
    {
        return array_map(
            fn (self $s) => ['value' => $s->value, 'label' => $s->label()],
            self::cases()
        );
    }
}
