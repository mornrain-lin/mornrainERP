<?php

namespace App\Http\Controllers;

use App\Enums\POStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\Inventory\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 采购管理
 *
 * 采购单草稿 → 已下单 → 已入库（回写库存）；支持从「库存采购建议」一键生成。
 */
class PurchaseController extends Controller
{
    public function index()
    {
        $orders = PurchaseOrder::with(['supplier', 'creator'])
            ->withCount('items')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('purchases.index', compact('orders'));
    }

    public function create()
    {
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $products = Product::orderBy('sku')->get(['id', 'sku', 'name', 'cost_price']);

        return view('purchases.create', compact('suppliers', 'products'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'status' => ['nullable', 'in:draft,ordered'],
            'order_date' => ['nullable', 'date'],
            'expected_at' => ['nullable', 'date'],
            'remark' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sku' => ['required', 'string', 'max:64'],
            'items.*.product_name' => ['nullable', 'string', 'max:160'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $po = DB::transaction(function () use ($data, $request) {
            $po = PurchaseOrder::create([
                'po_no' => $this->nextPoNo(),
                'supplier_id' => $data['supplier_id'],
                'status' => $data['status'] ?? POStatus::Draft->value,
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'expected_at' => $data['expected_at'] ?? null,
                'created_by' => auth()->id(),
                'remark' => $data['remark'] ?? null,
            ]);

            foreach ($data['items'] as $it) {
                $product = Product::where('sku', $it['sku'])->first();
                $po->items()->create([
                    'product_id' => $product?->id,
                    'sku' => $it['sku'],
                    'product_name' => $it['product_name'] ?? $product?->name ?? $it['sku'],
                    'quantity' => $it['quantity'],
                    'unit_cost' => $it['unit_cost'],
                    'received_qty' => 0,
                ]);
            }

            $po->recalcTotal();

            return $po;
        });

        return redirect()->route('purchases.show', $po)
            ->with('ok', "采购单 {$po->po_no} 已创建");
    }

    /**
     * 从库存采购建议一键生成采购单
     */
    public function fromSuggestions(Request $request, StockService $stock)
    {
        $suggestions = $stock->purchaseSuggestions(30);

        if ($suggestions->isEmpty()) {
            return back()->with('err', '当前没有需要补货的 SKU');
        }

        $supplier = Supplier::where('is_active', true)->orderBy('id')->first();
        if (! $supplier) {
            return back()->with('err', '请先到「基础资料」创建至少一个供应商');
        }

        $po = DB::transaction(function () use ($suggestions, $supplier) {
            $po = PurchaseOrder::create([
                'po_no' => $this->nextPoNo(),
                'supplier_id' => $supplier->id,
                'status' => POStatus::Draft->value,
                'order_date' => now()->toDateString(),
                'created_by' => auth()->id(),
                'remark' => '由库存采购建议自动生成',
            ]);

            foreach ($suggestions as $row) {
                $po->items()->create([
                    'product_id' => $row['product']->id,
                    'sku' => $row['product']->sku,
                    'product_name' => $row['product']->name,
                    'quantity' => $row['suggest'],
                    'unit_cost' => $row['product']->cost_price,
                    'received_qty' => 0,
                ]);
            }

            $po->recalcTotal();

            return $po;
        });

        return redirect()->route('purchases.show', $po)
            ->with('ok', "已根据采购建议生成采购单 {$po->po_no}");
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'creator', 'items.product']);

        return view('purchases.show', compact('purchaseOrder'));
    }

    /** 收货入库：回写库存并推进状态为已入库 */
    public function receive(Request $request, PurchaseOrder $purchaseOrder, StockService $stock): RedirectResponse
    {
        if (! $purchaseOrder->status->canReceive()) {
            return back()->with('err', "「{$purchaseOrder->status->label()}」状态的采购单不能收货");
        }

        $data = $request->validate([
            'received_at' => ['nullable', 'date'],
        ]);

        $receivedAt = $data['received_at'] ?? now()->toDateString();

        $summary = DB::transaction(function () use ($purchaseOrder, $stock, $receivedAt) {
            $moved = 0;
            foreach ($purchaseOrder->items as $item) {
                $product = $item->product ?? Product::where('sku', $item->sku)->first();
                if (! $product) {
                    continue;
                }
                $stock->adjust(
                    $product,
                    (int) $item->quantity,
                    StockMovement::TYPE_PO_IN,
                    "采购入库 {$purchaseOrder->po_no}（{$item->sku} × {$item->quantity}）",
                    null,
                    auth()->id(),
                );
                $item->update(['received_qty' => $item->quantity]);
                $moved++;
            }

            $purchaseOrder->update([
                'status' => POStatus::Received->value,
                'received_at' => $receivedAt,
            ]);

            return $moved;
        });

        return redirect()->route('purchases.show', $purchaseOrder)
            ->with('ok', "采购单 {$purchaseOrder->po_no} 已入库，回写 {$summary} 个 SKU 库存");
    }

    private function nextPoNo(): string
    {
        return 'PO' . now()->format('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 4));
    }
}
