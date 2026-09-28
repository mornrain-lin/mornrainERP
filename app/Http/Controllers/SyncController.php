<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\SyncLog;
use App\Services\Audit\AuditService;
use App\Services\PlatformSync\OrderSyncService;
use App\Services\PlatformSync\PlatformConnectorFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 平台对接（OpenAPI 自动拉单）
 */
class SyncController extends Controller
{
    public function index(PlatformConnectorFactory $factory)
    {
        $shops = Shop::with('platform')->withCount('orders')->orderBy('id')->get();
        $logs = SyncLog::with('shop')->latest()->limit(20)->get();

        $connectors = $shops->mapWithKeys(fn (Shop $s) => [$s->id => $factory->labelFor($s)]);

        return view('sync.index', compact('shops', 'logs', 'connectors'));
    }

    public function saveConfig(Request $request, Shop $shop): RedirectResponse
    {
        $data = $request->validate([
            'api_base' => ['nullable', 'url', 'max:255'],
            'app_key' => ['nullable', 'string', 'max:128'],
            'app_secret' => ['nullable', 'string', 'max:255'],
            'sync_enabled' => ['nullable', 'boolean'],
        ], [
            'api_base.url' => 'API 根地址必须是合法 URL',
        ]);

        $payload = [
            'api_base' => $data['api_base'] ?? null,
            'app_key' => $data['app_key'] ?? null,
        ];

        // 密钥留空表示不修改，避免误清空已配置凭证
        if (! empty($data['app_secret'])) {
            $payload['app_secret'] = $data['app_secret'];
        }

        $payload['sync_enabled'] = (bool) ($data['sync_enabled'] ?? false);

        $shop->forceFill($payload)->save();

        app(AuditService::class)->log('sync.config', "保存店铺 {$shop->name} 的平台对接凭证", $shop);

        return back()->with('ok', "店铺 {$shop->name} 的对接配置已保存");
    }

    public function run(Request $request, Shop $shop, OrderSyncService $service): RedirectResponse
    {
        $days = max(1, min(30, (int) $request->input('days', 3)));

        $result = $service->syncShop($shop, now()->subDays($days), now(), 'manual');

        app(AuditService::class)->log(
            'sync.run',
            "拉单（{$shop->name}）：" . $result['message'],
            $shop
        );

        return back()->with(
            $result['status'] === 'success' ? 'ok' : 'err',
            "{$shop->name}：" . $result['message']
        );
    }

    public function runAll(OrderSyncService $service): RedirectResponse
    {
        $results = $service->syncAll('manual');

        if ($results === []) {
            return back()->with('err', '没有已启用自动拉单的店铺，请先配置并开启');
        }

        $created = collect($results)->sum(fn ($r) => $r['result']['created']);
        $updated = collect($results)->sum(fn ($r) => $r['result']['updated']);
        $failed = collect($results)->filter(fn ($r) => $r['result']['status'] !== 'success')->count();

        app(AuditService::class)->log(
            'sync.run',
            sprintf('批量拉单 %d 个店铺：新建 %d 单，更新 %d 单，失败 %d 个', count($results), $created, $updated, $failed)
        );

        return back()->with(
            $failed > 0 ? 'err' : 'ok',
            sprintf('已同步 %d 个店铺：新建 %d 单，更新 %d 单，失败 %d 个店铺', count($results), $created, $updated, $failed)
        );
    }
}
