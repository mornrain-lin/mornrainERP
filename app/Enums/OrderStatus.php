<?php

namespace App\Enums;

/**
 * 订单状态机
 *
 * pending 待付款 → paid 待发货 → shipped 已发货 → completed 已完成
 * 任意非终态可进入 refunding 退款中 → refunded 已退款
 * pending / paid 可 cancelled 已取消
 */
enum OrderStatus: string
{
    case Pending = 'pending';       // 待付款
    case Paid = 'paid';             // 待发货（已付款）
    case Shipped = 'shipped';       // 已发货
    case Completed = 'completed';   // 已完成
    case Cancelled = 'cancelled';   // 已取消
    case Refunding = 'refunding';   // 退款中
    case Refunded = 'refunded';     // 已退款

    public function label(): string
    {
        return match ($this) {
            self::Pending => '待付款',
            self::Paid => '待发货',
            self::Shipped => '已发货',
            self::Completed => '已完成',
            self::Cancelled => '已取消',
            self::Refunding => '退款中',
            self::Refunded => '已退款',
        };
    }

    /** UI 徽标配色（对应 public/css/app.css 的 .badge-* ） */
    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warn',
            self::Paid => 'info',
            self::Shipped => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'muted',
            self::Refunding => 'warn',
            self::Refunded => 'danger',
        };
    }

    /** 允许流转到的下一状态 */
    public function nextStates(): array
    {
        return match ($this) {
            self::Pending => [self::Paid, self::Cancelled],
            self::Paid => [self::Shipped, self::Cancelled, self::Refunding],
            self::Shipped => [self::Completed, self::Refunding],
            self::Completed => [self::Refunding],
            self::Refunding => [self::Refunded, self::Paid],
            self::Refunded, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->nextStates(), true);
    }

    public function isTerminal(): bool
    {
        return $this->nextStates() === [];
    }

    /** 有效订单（计入营收统计） */
    public function countsAsRevenue(): bool
    {
        return in_array($this, [self::Paid, self::Shipped, self::Completed], true);
    }

    /** 所有状态，供下拉框使用 */
    public static function options(): array
    {
        return array_map(
            fn (self $s) => ['value' => $s->value, 'label' => $s->label(), 'tone' => $s->tone()],
            self::cases()
        );
    }
}
