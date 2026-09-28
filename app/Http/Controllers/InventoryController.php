<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Audit\AuditService;
use App\Services\Inventory\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 库存与采购
 *
 * 库存总览 / 安全库存预警 / 采购建议 / 入库出入库盘点
 */
class InventoryController extends Controller
{
    public function index(StockService $stock)
    {
        $products = Product::withCount(['orderItems as sold_count' => function ($q) {
            $q->select(\Illuminate\Support\Facades\DB::raw('COALESCE(SUM(quantity),0)'));
        }])->orderBy('stock')->orderBy('sku')->paginate(30);

        $all = Product::all();

        $summary = [
            'sku_count' => $all->count(),
            'stock_value' => round($all->sum(fn (Product $p) => max(0, $p->stock) * (float) $p->cost_price), 2),
            'low_count' => $stock->lowStockProducts()->count(),
            'out_count' => $all->where('stock', '<=', 0)->count(),
        ];

        return view('inventory.index', [
            'products' => $products,
            'summary' => $summary,
            'lowStock' => $stock->lowStockProducts(),
            'suggestions' => $stock->purchaseSuggestions(30),
            'movements' => StockMovement::with(['product', 'user'])->latest()->limit(15)->get(),
        ]);
    }

    public function adjust(Request $request, Product $product, StockService $stock): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:in,out,adjust'],
            'quantity' => ['required', 'integer', 'min:1'],
            'remark' => ['nullable', 'string', 'max:255'],
        ], [
            'type.in' => '变动类型不正确',
            'quantity.min' => '数量必须大于 0',
        ]);

        $quantity = (int) $data['quantity'];

        $delta = match ($data['type']) {
            'in' => $quantity,
            'out' => -1 * $quantity,
            default => $quantity - (int) $product->stock, // 盘点：直接把库存设成目标值
        };

        if ($delta === 0) {
            return back()->with('err', '库存数量没有变化');
        }

        $stock->adjust(
            $product,
            $delta,
            $data['type'],
            $data['remark'] ?? null,
            null,
            auth()->id(),
        );

        app(AuditService::class)->log(
            'inventory.adjusted',
            "{$product->sku} 库存调整（{$data['type']} {$quantity}），当前 {$product->fresh()->stock} 件",
            $product
        );

        return back()->with('ok', "{$product->sku} 库存已调整，当前 {$product->fresh()->stock} 件");
    }
}
