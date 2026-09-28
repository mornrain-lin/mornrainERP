<?php

namespace App\Services\Quota;

use Exception;

/**
 * 月度订单额度已用尽。
 *
 * 由 QuotaService::assertCanCreate() 抛出，调用方捕获后向用户展示升级引导。
 */
class QuotaExceededException extends Exception
{
    public function __construct(public readonly int $quota, public readonly int $used)
    {
        parent::__construct(
            "本月订单额度已用完（{$used}/{$quota}）。升级付费版可解除限制，或等待下月 1 日自动重置。"
        );
    }
}
