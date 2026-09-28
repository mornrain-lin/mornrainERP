<?php

namespace App\Services\Quota;

use App\Models\Order;
use Carbon\Carbon;

/**
 * 月度订单额度服务
 *
 * 统计口径：以 orders.created_at 落在自然月内的订单数为准。
 * 免费版默认 400 单/月；config('plan.monthly_order_quota') = 0 表示不限量。
 */
class QuotaService
{
    /** 月度订单额度；0 表示不限量（付费版） */
    public function monthlyQuota(): int
    {
        return (int) config('plan.monthly_order_quota', 400);
    }

    public function planName(): string
    {
        return (string) config('plan.name', 'free');
    }

    public function isLimited(): bool
    {
        return $this->monthlyQuota() > 0;
    }

    public function usedThisMonth(?int $year = null, ?int $month = null): int
    {
        $year ??= Carbon::now()->year;
        $month ??= Carbon::now()->month;
        $from = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $to = $from->copy()->endOfMonth();

        return Order::whereBetween('created_at', [$from, $to])->count();
    }

    /** 剩余额度；不限量时返回 null */
    public function remaining(): ?int
    {
        if (! $this->isLimited()) {
            return null;
        }

        return max(0, $this->monthlyQuota() - $this->usedThisMonth());
    }

    public function exceeded(): bool
    {
        if (! $this->isLimited()) {
            return false;
        }

        return $this->usedThisMonth() >= $this->monthlyQuota();
    }

    /** 是否还能再新建 $count 单 */
    public function canCreate(int $count = 1): bool
    {
        $remaining = $this->remaining();

        return $remaining === null || $remaining >= $count;
    }

    /** 额度不足时抛出 QuotaExceededException */
    public function assertCanCreate(int $count = 1): void
    {
        if (! $this->canCreate($count)) {
            throw new QuotaExceededException($this->monthlyQuota(), $this->usedThisMonth());
        }
    }
}
