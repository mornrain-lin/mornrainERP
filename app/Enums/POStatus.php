<?php

namespace App\Enums;

/**
 * 采购单状态机
 *
 * draft 草稿 → ordered 已下单 → received 已入库
 * 任意状态可 cancelled 已取消
 * 仅 received 会回写库存
 */
enum POStatus: string
{
    case Draft = 'draft';         // 草稿
    case Ordered = 'ordered';     // 已下单
    case Received = 'received';   // 已入库
    case Cancelled = 'cancelled'; // 已取消

    public function label(): string
    {
        return match ($this) {
            self::Draft => '草稿',
            self::Ordered => '已下单',
            self::Received => '已入库',
            self::Cancelled => '已取消',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'muted',
            self::Ordered => 'warn',
            self::Received => 'success',
            self::Cancelled => 'danger',
        };
    }

    /** 是否允许推进到收货入库 */
    public function canReceive(): bool
    {
        return in_array($this, [self::Draft, self::Ordered], true);
    }

    public static function options(): array
    {
        return array_map(
            fn (self $s) => ['value' => $s->value, 'label' => $s->label(), 'tone' => $s->tone()],
            self::cases()
        );
    }
}
