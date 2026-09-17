<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    /**
     * 订单列表：支持平台 / 店铺 / 状态 / 关键词 / 日期区间 多维筛选
     */
    public function index(Request $request)
    {
        $filters = $request->only([
            'platform_id', 'shop_id', 'status', 'q', 'date_from', 'date_to', 'sort',
        ]);

        $orders = Order::query()
            ->with(['platform', 'shop'])
            ->withAggregates()
            ->platform($filters['platform_id'] ?? null)
            ->shop($filters['shop_id'] ?? null)
            ->status($filters['status'] ?? null)
            ->search($filters['q'] ?? null)
            ->betweenDates($filters['date_from'] ?? null, $filters['date_to'] ?? null)
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        // 当前筛选条件下的利润合计
        $all = (clone $orders)->getCollection();
        $sumRevenue = 0.0;
        $sumProfit = 0.0;
        foreach ($all as $o) {
            $p = $o->profit();
            $sumRevenue += $p['revenue'];
            $sumProfit += $p['profit'];
        }

        return view('orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'platforms' => Platform::orderBy('id')->get(),
            'shops' => Shop::with('platform')->orderBy('id')->get(),
            'statuses' => OrderStatus::options(),
            'pageRevenue' => round($sumRevenue, 2),
            'pageProfit' => round($sumProfit, 2),
        ]);
    }

    public function create()
    {
        return view('orders.create', [
            'platforms' => Platform::where('is_active', true)->orderBy('id')->get(),
            'shops' => Shop::with('platform')->where('is_active', true)->orderBy('id')->get(),
            'products' => Product::where('is_active', true)->orderBy('sku')->get(),
            'statuses' => OrderStatus::options(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateOrder($request);

        $order = DB::transaction(function () use ($data) {
            $platform = Platform::findOrFail($data['platform_id']);
            $shop = Shop::findOrFail($data['shop_id']);

            $order = Order::create([
                'order_no' => $data['order_no'],
                'platform_id' => $platform->id,
                'shop_id' => $shop->id,
                'buyer_name' => $data['buyer_name'] ?? null,
                'buyer_country' => $data['buyer_country'] ?? $shop->region,
                'currency' => $data['currency'] ?? $shop->currency,
                'exchange_rate' => $data['exchange_rate'] ?? $shop->exchange_rate,
                'goods_amount' => $data['goods_amount'],
                'shipping_income' => $data['shipping_income'] ?? 0,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'platform_commission' => $data['platform_commission']
                    ?? round($data['goods_amount'] * $platform->commission_rate / 100, 2),
                'payment_fee' => $data['payment_fee']
                    ?? round($data['goods_amount'] * $platform->payment_fee_rate / 100, 2),
                'refund_amount' => $data['refund_amount'] ?? 0,
                'shipping_cost' => $data['shipping_cost'] ?? 0,
                'ad_cost' => $data['ad_cost'] ?? 0,
                'other_cost' => $data['other_cost'] ?? 0,
                'status' => $data['status'],
                'source' => 'manual',
                'paid_at' => $data['status'] !== OrderStatus::Pending->value ? now() : null,
                'remark' => $data['remark'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $product = Product::where('sku', $item['sku'])->first();
                $order->items()->create([
                    'product_id' => $product?->id,
                    'sku' => $item['sku'],
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'unit_cost' => $item['unit_cost'] ?? $product?->cost_price ?? 0,
                ]);
            }

            return $order;
        });

        return redirect()->route('orders.show', $order)
            ->with('ok', "订单 {$order->order_no} 已创建");
    }

    public function show(Order $order)
    {
        $order->load(['platform', 'shop', 'items.product', 'shipments']);
        $profit = $order->profit();

        return view('orders.show', [
            'order' => $order,
            'profit' => $profit,
            'statuses' => OrderStatus::options(),
            'nextStates' => $order->status->nextStates(),
            'carriers' => Shipment::CARRIERS,
            'shipmentStatuses' => ShipmentStatus::options(),
        ]);
    }

    public function edit(Order $order)
    {
        $order->load('items');

        return view('orders.edit', [
            'order' => $order,
            'platforms' => Platform::orderBy('id')->get(),
            'shops' => Shop::with('platform')->orderBy('id')->get(),
            'statuses' => OrderStatus::options(),
        ]);
    }

    public function update(Request $request, Order $order)
    {
        $data = $request->validate([
            'buyer_name' => 'nullable|string|max:96',
            'buyer_country' => 'nullable|string|max:8',
            'goods_amount' => 'required|numeric|min:0',
            'shipping_income' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'platform_commission' => 'nullable|numeric|min:0',
            'payment_fee' => 'nullable|numeric|min:0',
            'refund_amount' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'ad_cost' => 'nullable|numeric|min:0',
            'other_cost' => 'nullable|numeric|min:0',
            'remark' => 'nullable|string',
        ]);

        $order->update($data);

        return redirect()->route('orders.show', $order)->with('ok', '订单已更新');
    }

    public function destroy(Order $order)
    {
        $order->delete();

        return redirect()->route('orders.index')->with('ok', "订单 {$order->order_no} 已删除");
    }

    /** 单条状态流转（带状态机校验） */
    public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => 'required|string',
            'remark' => 'nullable|string',
        ]);

        $target = OrderStatus::tryFrom($data['status']);
        if (! $target) {
            return back()->with('err', '无效的订单状态');
        }
        if (! $order->status->canTransitionTo($target)) {
            return back()->with('err', "「{$order->status->label()}」不能直接流转到「{$target->label()}」");
        }

        $payload = ['status' => $target];
        if ($target === OrderStatus::Paid && ! $order->paid_at) {
            $payload['paid_at'] = now();
        }
        if ($target === OrderStatus::Shipped && ! $order->shipped_at) {
            $payload['shipped_at'] = now();
        }
        if ($target === OrderStatus::Completed) {
            $payload['completed_at'] = now();
        }
        if ($target === OrderStatus::Refunded && ! $order->refund_amount) {
            $payload['refund_amount'] = $order->goods_amount;
        }
        if (! empty($data['remark'])) {
            $payload['remark'] = trim(($order->remark ? $order->remark . "\n" : '') . $data['remark']);
        }

        $order->update($payload);

        return back()->with('ok', "订单状态已更新为「{$target->label()}」");
    }

    /** 订单发货：写入一条物流记录并推进状态 */
    public function ship(Request $request, Order $order)
    {
        $data = $request->validate([
            'carrier' => 'required|string|max:64',
            'tracking_no' => 'required|string|max:96',
            'cost' => 'nullable|numeric|min:0',
        ]);

        if (! in_array($order->status, [OrderStatus::Paid, OrderStatus::Pending], true)) {
            return back()->with('err', '仅「待发货 / 待付款」订单可以发货');
        }

        DB::transaction(function () use ($order, $data) {
            Shipment::create([
                'order_id' => $order->id,
                'carrier' => $data['carrier'],
                'tracking_no' => $data['tracking_no'],
                'status' => ShipmentStatus::InTransit,
                'cost' => $data['cost'] ?? 0,
                'shipped_at' => now(),
            ]);

            $order->update([
                'status' => OrderStatus::Shipped,
                'shipped_at' => $order->shipped_at ?? now(),
                'paid_at' => $order->paid_at ?? now(),
                'shipping_cost' => $data['cost'] ?? $order->shipping_cost,
            ]);
        });

        return back()->with('ok', "订单 {$order->order_no} 已发货");
    }

    /** 批量发货：一次为多个订单填写运单号 */
    public function batchShip(Request $request)
    {
        $data = $request->validate([
            'carrier' => 'required|string|max:64',
            'tracking_nos' => 'required|string',
        ]);

        $lines = preg_split('/\r\n|\r|\n/', trim($data['tracking_nos']));
        $lines = array_values(array_filter(array_map('trim', $lines)));
        if ($lines === []) {
            return back()->with('err', '请至少填写一行「订单号,运单号」');
        }

        $ok = 0;
        $fail = [];
        foreach ($lines as $line) {
            $parts = preg_split('/[,\t]+/', $line);
            if (count($parts) < 2) {
                $fail[] = "格式错误：{$line}";
                continue;
            }
            [$orderNo, $trackingNo] = [trim($parts[0]), trim($parts[1])];
            $order = Order::where('order_no', $orderNo)->first();
            if (! $order) {
                $fail[] = "订单不存在：{$orderNo}";
                continue;
            }
            if (! in_array($order->status, [OrderStatus::Paid, OrderStatus::Pending], true)) {
                $fail[] = "状态不可发货：{$orderNo}（{$order->status->label()}）";
                continue;
            }
            DB::transaction(function () use ($order, $data, $trackingNo) {
                Shipment::create([
                    'order_id' => $order->id,
                    'carrier' => $data['carrier'],
                    'tracking_no' => $trackingNo,
                    'status' => ShipmentStatus::InTransit,
                    'shipped_at' => now(),
                ]);
                $order->update([
                    'status' => OrderStatus::Shipped,
                    'shipped_at' => now(),
                    'paid_at' => $order->paid_at ?? now(),
                ]);
            });
            $ok++;
        }

        $msg = "批量发货完成：成功 {$ok} 单";
        if ($fail) {
            $msg .= '，失败 ' . count($fail) . ' 单（' . implode('；', array_slice($fail, 0, 3)) . '）';
        }

        return back()->with($ok ? 'ok' : 'err', $msg);
    }

    public function importForm()
    {
        return view('orders.import', [
            'sample' => $this->sampleCsv(),
        ]);
    }

    /** CSV 导入订单：同一 order_no 的多行自动合并为一张订单 + 多条明细 */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:4096',
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');
        if (! $handle) {
            return back()->with('err', '无法读取文件');
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);

            return back()->with('err', 'CSV 为空');
        }
        $header = array_map(fn ($h) => trim((string) $h, " \t\n\r\0\xEF\xBB\xBF"), $header);

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) === 1 && trim((string) $line[0]) === '') {
                continue;
            }
            $rows[] = array_combine($header, array_pad($line, count($header), null));
        }
        fclose($handle);

        $created = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($rows, &$created, &$skipped, &$errors) {
            $grouped = [];
            foreach ($rows as $r) {
                $no = trim((string) ($r['order_no'] ?? ''));
                if ($no === '') {
                    $errors[] = '存在缺少 order_no 的行，已跳过';
                    $skipped++;
                    continue;
                }
                $grouped[$no][] = $r;
            }

            foreach ($grouped as $no => $items) {
                if (Order::where('order_no', $no)->exists()) {
                    $skipped++;
                    continue;
                }
                $first = $items[0];
                $platform = Platform::where('code', trim((string) ($first['platform_code'] ?? '')))->first();
                if (! $platform) {
                    $errors[] = "平台不存在：{$first['platform_code']}（订单 {$no}）";
                    $skipped++;
                    continue;
                }
                $shop = Shop::firstOrCreate(
                    ['platform_id' => $platform->id, 'name' => trim((string) ($first['shop_name'] ?? '默认店铺'))],
                    [
                        'region' => $first['buyer_country'] ?? null,
                        'currency' => $first['currency'] ?? 'CNY',
                        'exchange_rate' => (float) ($first['exchange_rate'] ?? 1),
                    ]
                );

                $goods = (float) ($first['goods_amount'] ?? 0);
                $status = OrderStatus::tryFrom(trim((string) ($first['status'] ?? 'paid'))) ?? OrderStatus::Paid;

                $order = Order::create([
                    'order_no' => $no,
                    'platform_id' => $platform->id,
                    'shop_id' => $shop->id,
                    'buyer_name' => $first['buyer_name'] ?? null,
                    'buyer_country' => $first['buyer_country'] ?? $shop->region,
                    'currency' => $first['currency'] ?? $shop->currency,
                    'exchange_rate' => (float) ($first['exchange_rate'] ?? $shop->exchange_rate),
                    'goods_amount' => $goods,
                    'shipping_income' => (float) ($first['shipping_income'] ?? 0),
                    'discount_amount' => (float) ($first['discount_amount'] ?? 0),
                    'platform_commission' => (float) ($first['platform_commission'] ?? round($goods * $platform->commission_rate / 100, 2)),
                    'payment_fee' => (float) ($first['payment_fee'] ?? round($goods * $platform->payment_fee_rate / 100, 2)),
                    'refund_amount' => (float) ($first['refund_amount'] ?? 0),
                    'shipping_cost' => (float) ($first['shipping_cost'] ?? 0),
                    'ad_cost' => (float) ($first['ad_cost'] ?? 0),
                    'other_cost' => (float) ($first['other_cost'] ?? 0),
                    'status' => $status,
                    'source' => 'import',
                    'paid_at' => $status !== OrderStatus::Pending ? now() : null,
                ]);

                foreach ($items as $it) {
                    $sku = trim((string) ($it['sku'] ?? ''));
                    if ($sku === '') {
                        continue;
                    }
                    $product = Product::where('sku', $sku)->first();
                    $order->items()->create([
                        'product_id' => $product?->id,
                        'sku' => $sku,
                        'product_name' => $it['product_name'] ?? $product?->name ?? $sku,
                        'quantity' => (int) ($it['quantity'] ?? 1),
                        'unit_price' => (float) ($it['unit_price'] ?? 0),
                        'unit_cost' => (float) ($it['unit_cost'] ?? $product?->cost_price ?? 0),
                    ]);
                }
                $created++;
            }
        });

        $msg = "导入完成：新增 {$created} 张订单，跳过 {$skipped} 行";
        if ($errors) {
            $msg .= '（' . implode('；', array_slice(array_unique($errors), 0, 3)) . '）';
        }

        return redirect()->route('orders.index')->with($created ? 'ok' : 'err', $msg);
    }

    /** 导出订单（含利润核算结果）为 CSV */
    public function export(Request $request): StreamedResponse
    {
        $filters = $request->only(['platform_id', 'shop_id', 'status', 'q', 'date_from', 'date_to']);

        $query = Order::query()->with(['platform', 'shop', 'items'])->withAggregates()
            ->platform($filters['platform_id'] ?? null)
            ->shop($filters['shop_id'] ?? null)
            ->status($filters['status'] ?? null)
            ->search($filters['q'] ?? null)
            ->betweenDates($filters['date_from'] ?? null, $filters['date_to'] ?? null)
            ->orderByDesc('created_at');

        $filename = 'mornrainERP-orders-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM，Excel 打开中文不乱码
            fputcsv($out, [
                '订单号', '平台', '店铺', '买家', '国家', '币种', '汇率',
                '商品金额', '运费收入', '平台佣金', '支付手续费', '退款',
                '物流成本', '广告成本', '其他成本',
                '营收(CNY)', '成本(CNY)', '毛利(CNY)', '毛利率(%)', '状态', '下单时间',
            ]);
            $query->chunk(500, function ($orders) use ($out) {
                foreach ($orders as $o) {
                    $p = $o->profit();
                    fputcsv($out, [
                        $o->order_no, $o->platform?->name, $o->shop?->name,
                        $o->buyer_name, $o->buyer_country, $o->currency, $o->exchange_rate,
                        $o->goods_amount, $o->shipping_income, $o->platform_commission,
                        $o->payment_fee, $o->refund_amount,
                        $o->shipping_cost, $o->ad_cost, $o->other_cost,
                        $p['revenue'], $p['cost'], $p['profit'], $p['margin'],
                        $o->status->label(), $o->created_at->format('Y-m-d H:i'),
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * 模拟平台同步：按现有店铺/SKU 随机生成订单，
     * 用于演示「平台订单自动归集」链路（真实环境替换为各平台 OpenAPI 拉单）
     */
    public function simulate(Request $request)
    {
        $count = (int) $request->input('count', 8);
        $count = max(1, min(50, $count));

        $shops = Shop::with('platform')->where('is_active', true)->get();
        $products = Product::where('is_active', true)->get();
        if ($shops->isEmpty() || $products->isEmpty()) {
            return back()->with('err', '请先准备店铺与商品数据');
        }

        $currencies = ['MYR' => 0.65, 'THB' => 0.20, 'SGD' => 5.35, 'USD' => 7.20, 'IDR' => 0.00045, 'PHP' => 0.128];
        $statuses = [OrderStatus::Pending, OrderStatus::Paid, OrderStatus::Paid, OrderStatus::Shipped, OrderStatus::Completed, OrderStatus::Refunding];
        $carriers = Shipment::CARRIERS;

        DB::transaction(function () use ($count, $shops, $products, $currencies, $statuses, $carriers) {
            for ($i = 0; $i < $count; $i++) {
                $shop = $shops->random();
                $platform = $shop->platform;
                $cur = array_rand($currencies);
                $rate = $currencies[$cur];
                $status = $statuses[array_rand($statuses)];

                $goods = 0.0;
                $lineItems = [];
                $n = random_int(1, 3);
                for ($j = 0; $j < $n; $j++) {
                    $product = $products->random();
                    $qty = random_int(1, 3);
                    $price = round($product->cost_price * $rate > 0 ? ($product->cost_price * random_int(250, 420) / 100) / $rate : 20, 2);
                    $lineItems[] = compact('product', 'qty', 'price');
                    $goods += $price * $qty;
                }
                $goods = round($goods, 2);
                $shippingIncome = round(random_int(0, 30) / 10, 2);
                $commission = round($goods * $platform->commission_rate / 100, 2);
                $paymentFee = round($goods * $platform->payment_fee_rate / 100, 2);
                $shippingCost = round(random_int(80, 320) / 10, 2);
                $adCost = round(random_int(0, 150) / 10, 2);
                $isRefund = in_array($status, [OrderStatus::Refunding], true);

                $order = Order::create([
                    'order_no' => strtoupper($platform->code) . '-' . now()->format('ymd') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
                    'platform_id' => $platform->id,
                    'shop_id' => $shop->id,
                    'buyer_name' => $this->randomBuyer(),
                    'buyer_country' => $shop->region,
                    'currency' => $cur,
                    'exchange_rate' => $rate,
                    'goods_amount' => $goods,
                    'shipping_income' => $shippingIncome,
                    'discount_amount' => 0,
                    'platform_commission' => $commission,
                    'payment_fee' => $paymentFee,
                    'refund_amount' => $isRefund ? $goods : 0,
                    'shipping_cost' => $shippingCost,
                    'ad_cost' => $adCost,
                    'other_cost' => 0,
                    'status' => $status,
                    'source' => 'api',
                    'paid_at' => $status !== OrderStatus::Pending ? now()->subHours(random_int(1, 72)) : null,
                    'shipped_at' => in_array($status, [OrderStatus::Shipped, OrderStatus::Completed], true) ? now()->subHours(random_int(1, 40)) : null,
                    'completed_at' => $status === OrderStatus::Completed ? now()->subHours(random_int(1, 10)) : null,
                    'created_at' => now()->subHours(random_int(0, 24 * 14)),
                ]);

                foreach ($lineItems as $li) {
                    $order->items()->create([
                        'product_id' => $li['product']->id,
                        'sku' => $li['product']->sku,
                        'product_name' => $li['product']->name,
                        'quantity' => $li['qty'],
                        'unit_price' => $li['price'],
                        'unit_cost' => $li['product']->cost_price,
                    ]);
                }

                if (in_array($status, [OrderStatus::Shipped, OrderStatus::Completed], true)) {
                    Shipment::create([
                        'order_id' => $order->id,
                        'carrier' => $carriers[array_rand($carriers)],
                        'tracking_no' => 'MR' . strtoupper(bin2hex(random_bytes(5))),
                        'status' => $status === OrderStatus::Completed ? ShipmentStatus::Delivered : ShipmentStatus::InTransit,
                        'cost' => $shippingCost,
                        'shipped_at' => now()->subHours(random_int(1, 40)),
                        'delivered_at' => $status === OrderStatus::Completed ? now()->subHours(random_int(1, 8)) : null,
                    ]);
                }
            }
        });

        return back()->with('ok', "模拟同步完成：已从平台拉取 {$count} 笔新订单");
    }

    // ---------------- helpers ----------------

    private function validateOrder(Request $request): array
    {
        return $request->validate([
            'order_no' => 'required|string|max:64|unique:orders,order_no',
            'platform_id' => 'required|exists:platforms,id',
            'shop_id' => 'required|exists:shops,id',
            'buyer_name' => 'nullable|string|max:96',
            'buyer_country' => 'nullable|string|max:8',
            'currency' => 'nullable|string|max:8',
            'exchange_rate' => 'nullable|numeric|min:0',
            'goods_amount' => 'required|numeric|min:0',
            'shipping_income' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'platform_commission' => 'nullable|numeric|min:0',
            'payment_fee' => 'nullable|numeric|min:0',
            'refund_amount' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'ad_cost' => 'nullable|numeric|min:0',
            'other_cost' => 'nullable|numeric|min:0',
            'status' => 'required|string',
            'remark' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.sku' => 'required|string|max:64',
            'items.*.product_name' => 'required|string|max:160',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
        ]);
    }

    private function randomBuyer(): string
    {
        $surnames = ['Ahmad', 'Siti', 'Nguyen', 'Somchai', 'Putra', 'Maria', 'Tan', 'Chen', 'Rahman', 'Lina'];
        $names = ['Ali', 'Budi', 'Mai', 'Ken', 'Joy', 'Ayu', 'Rio', 'Dewi', 'Hakim', 'Nadia'];

        return $surnames[array_rand($surnames)] . ' ' . $names[array_rand($names)];
    }

    private function sampleCsv(): string
    {
        return implode("\n", [
            'order_no,platform_code,shop_name,buyer_name,buyer_country,currency,exchange_rate,goods_amount,shipping_income,discount_amount,platform_commission,payment_fee,refund_amount,shipping_cost,ad_cost,status,sku,product_name,quantity,unit_price',
            'SP-240101-0001,shopee,晨雨官方店,Ahmad Ali,MY,MYR,0.65,120.00,5.00,0,7.20,2.40,0,18.50,3.00,paid,MR-0001,硅胶折叠水杯,1,120.00',
            'SP-240101-0001,shopee,晨雨官方店,Ahmad Ali,MY,MYR,0.65,120.00,5.00,0,7.20,2.40,0,18.50,3.00,paid,MR-0002,便携露营灯,1,0',
        ]);
    }
}
