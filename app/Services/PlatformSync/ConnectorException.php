<?php

namespace App\Services\PlatformSync;

class ConnectorException extends \RuntimeException
{
    public static function misconfigured(string $why): self
    {
        return new self('店铺未完成 API 配置：' . $why);
    }

    public static function requestFailed(string $why): self
    {
        return new self('平台接口调用失败：' . $why);
    }

    public static function badResponse(string $why): self
    {
        return new self('平台返回数据无法解析：' . $why);
    }
}
