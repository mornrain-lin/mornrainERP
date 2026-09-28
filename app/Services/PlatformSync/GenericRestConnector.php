<?php

namespace App\Services\PlatformSync;

use App\Models\Shop;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;

/**
 * 通用 REST 连接器
 *
 * 约定平台侧提供 GET {api_base}/orders 接口，返回：
 * {
 *   "data": [ { "order_no": "...", "buyer": {...}, "goods_amount": 1.0, "items": [...] } ],
 *   "has_more": false, "next_page": 2
 * }
 *
 * 鉴权：X-Api-Key + X-Timestamp + X-Signature(HMAC-SHA256)
 *      签名原文 = app_key + "\n" + timestamp + "\n" + path
 * 兼容已持有令牌的平台：额外带 X-Access-Token。
 *
 * 各家平台（Shopee / TikTok Shop / Amazon SP-API / Lazada）只需实现
 * PlatformConnector 接口，或按上述契约做一层网关，即可直接接入。
 */
class GenericRestConnector implements PlatformConnector
{
    public const PATH_ORDERS = '/orders';
    public const TIMEOUT = 20;
    public const PAGE_SIZE = 100;
    public const MAX_PAGES = 20;

    public function label(): string
    {
        return '通用 REST OpenAPI';
    }

    public function fetchOrders(Shop $shop, CarbonInterface $from, CarbonInterface $to): array
    {
        if (! $shop->api_base || ! $shop->app_key) {
            throw ConnectorException::misconfigured('缺少 api_base 或 app_key');
        }

        $orders = [];
        $page = 1;

        do {
            $payload = $this->request($shop, [
                'updated_from' => $from->toIso8601String(),
                'updated_to' => $to->toIso8601String(),
                'page' => $page,
                'page_size' => self::PAGE_SIZE,
            ]);

            foreach ((array) ($payload['data'] ?? []) as $raw) {
                if (! is_array($raw)) {
                    continue;
                }
                $order = NormalizedOrder::fromArray($raw);
                if ($order->isValid()) {
                    $orders[] = $order;
                }
            }

            $hasMore = (bool) ($payload['has_more'] ?? false);
            $page = (int) ($payload['next_page'] ?? ($page + 1));
        } while ($hasMore && $page <= self::MAX_PAGES);

        return $orders;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function request(Shop $shop, array $query): array
    {
        $timestamp = (string) now()->getTimestamp();
        $headers = [
            'Accept' => 'application/json',
            'X-Api-Key' => (string) $shop->app_key,
            'X-Timestamp' => $timestamp,
            'X-Signature' => $this->signature($shop, $timestamp),
        ];

        if ($shop->access_token) {
            $headers['X-Access-Token'] = (string) $shop->access_token;
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(self::TIMEOUT)
                ->connectTimeout(5)
                ->retry(2, 500)
                ->get(rtrim((string) $shop->api_base, '/') . self::PATH_ORDERS, $query);
        } catch (\Throwable $e) {
            throw ConnectorException::requestFailed($e->getMessage());
        }

        if (! $response->successful()) {
            throw ConnectorException::requestFailed('HTTP ' . $response->status());
        }

        $json = $response->json();

        if (! is_array($json) || ! array_key_exists('data', $json)) {
            throw ConnectorException::badResponse('响应缺少 data 字段');
        }

        return $json;
    }

    private function signature(Shop $shop, string $timestamp): string
    {
        $raw = $shop->app_key . "\n" . $timestamp . "\n" . self::PATH_ORDERS;

        return hash_hmac('sha256', $raw, (string) $shop->app_secret);
    }
}
