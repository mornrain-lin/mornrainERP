<?php

namespace App\Http\Controllers;

use App\Enums\POStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\Audit\AuditService;
use App\Services\Inventory\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 采购管理
 *
 * 草稿 → 已下单 → 已入库（回写库存）；草稿 / 已下单可取消。
 * 支持从「库存采购建议」一键生成，也支持手工录入与二次编辑。
 */
class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $supplierId = (string) $request->query('supplier_id', '');

        $orders = PurchaseOrder::with(['supplier', 'creator'])
            ->withCount('items')
            ->when($q !== '', function ($b) use ($q) {
                $b->where(function ($w) use ($q) {
                    $w->where('po_no', 'like', "%{$q}%")
                        ->orWhere('remark', 'like', "%{$q}%");
                });
            })
            ->when($status !== 'all', fn ($b) => $b->where('status', $status))
            ->when($supplierId !== '', fn ($b) => $b->where('supplier_id', (int) $supplierId))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);

        return view('purchases.index', compact('orders', 'suppliers', 'q', 'status', 'supplierId'));
    }

    public function create()
    {
        return $this->formView(new PurchaseOrder());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $po = DB::transaction(function () use ($data) {
            $po = PurchaseOrder::create([
                'po_no' => $this->nextPoNo(),
                'supplier_id' => $data['supplier_id'],
                'status' => $data['status'] ?? POStatus::Draft->value,
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'expected_at' => $data['expected_at'] ?? null,
                'created_by' => auth()->id(),
                'remark' => $data['remark'] ?? null,
            ]);

            $this->syncItems($po, $data['items']);

            return $po;
        });

        app(AuditService::class)->log(
            'purchase.created',
            "创建采购单 {$po->po_no}（{$po->supplier?->name} · ¥" . number_format($po->total_amount, 2) . '）',
            $po
        );

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
            return back()->with('err', '请先到「基础资料 → 供应商」创建至少一个启用中的供应商');
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

        app(AuditService::class)->log(
            'purchase.created',
            "按库存采购建议生成采购单 {$po->po_no}（{$po->items()->count()} 个 SKU）",
            $po
        );

        return redirect()->route('purchases.show', $po)
            ->with('ok', "已根据采购建议生成采购单 {$po->po_no}");
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'creator', 'items.product']);

        return view('purchases.show', compact('purchaseOrder'));
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        if ($reason = $this->editGuard($purchaseOrder)) {
            return redirect()->route('purchases.show', $purchaseOrder)->with('err', $reason);
        }

        return $this->formView($purchaseOrder);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($reason = $this->editGuard($purchaseOrder)) {
            return back()->with('err', $reason);
        }

        $data = $this->validated($request);

        DB::transaction(function () use ($purchaseOrder, $data) {
            $purchaseOrder->update([
                'supplier_id' => $data['supplier_id'],
                'status' => $data['status'] ?? $purchaseOrder->status->value,
                'order_date' => $data['order_date'] ?? $purchaseOrder->order_date,
                'expected_at' => $data['expected_at'] ?? null,
                'remark' => $data['remark'] ?? null,
            ]);

            $this->syncItems($purchaseOrder, $data['items']);
        });

        app(AuditService::class)->log(
            'purchase.updated',
            "修改采购单 {$purchaseOrder->po_no}（金额 ¥" . number_format($purchaseOrder->total_amount, 2) . '）',
            $purchaseOrder
        );

        return redirect()->route('purchases.show', $purchaseOrder)
            ->with('ok', "采购单 {$purchaseOrder->po_no} 已保存");
    }

    /** 草稿 → 已下单 */
    public function place(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status !== POStatus::Draft) {
            return back()->with('err', "仅「草稿」可标记下单，当前为「{$purchaseOrder->status->label()}」");
        }

        $purchaseOrder->update(['status' => POStatus::Ordered->value]);

        app(AuditService::class)->log('purchase.placed', "采购单 {$purchaseOrder->po_no} 标记为已下单", $purchaseOrder);

        return back()->with('ok', "采购单 {$purchaseOrder->po_no} 已标记为「已下单」");
    }

    /** 草稿 / 已下单 → 已取消 */
    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! in_array($purchaseOrder->status, [POStatus::Draft, POStatus::Ordered], true)) {
            return back()->with('err', "「{$purchaseOrder->status->label()}」状态的采购单不能取消");
        }

        $purchaseOrder->update(['status' => POStatus::Cancelled->value]);

        app(AuditService::class)->log('purchase.cancelled', "取消采购单 {$purchaseOrder->po_no}", $purchaseOrder);

        return back()->with('ok', "采购单 {$purchaseOrder->po_no} 已取消");
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! in_array($purchaseOrder->status, [POStatus::Draft, POStatus::Cancelled], true)) {
            return back()->with('err', "仅「草稿 / 已取消」可删除，「{$purchaseOrder->status->label()}」状态请改用取消或收货");
        }

        $no = $purchaseOrder->po_no;
        DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder->items()->delete();
            $purchaseOrder->delete();
        });

        app(AuditService::class)->log('purchase.deleted', "删除采购单 {$no}");

        return redirect()->route('purchases.index')->with('ok', "采购单 {$no} 已删除");
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

        app(AuditService::class)->log(
            'purchase.received',
            "采购单 {$purchaseOrder->po_no} 收货入库，回写 {$summary} 个 SKU 库存",
            $purchaseOrder
        );

        return redirect()->route('purchases.show', $purchaseOrder)
            ->with('ok', "采购单 {$purchaseOrder->po_no} 已入库，回写 {$summary} 个 SKU 库存");
    }

    /** 编辑前置校验：不可编辑时返回原因，可编辑返回 null */
    private function editGuard(PurchaseOrder $po): ?string
    {
        if (! in_array($po->status, [POStatus::Draft, POStatus::Ordered], true)) {
            return "「{$po->status->label()}」状态的采购单不可编辑";
        }

        if ($po->items()->where('received_qty', '>', 0)->exists()) {
            return '已有明细完成入库，不能再编辑（避免与库存流水对不上）';
        }

        return null;
    }

    private function formView(PurchaseOrder $po)
    {
        // 编辑时若原供应商已停用，也要出现在下拉里，否则会被静默改掉归属
        $suppliers = Supplier::query()
            ->where(function ($q) use ($po) {
                $q->where('is_active', true);
                if ($po->exists && $po->supplier_id) {
                    $q->orWhere('id', $po->supplier_id);
                }
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $products = Product::orderBy('sku')->get(['id', 'sku', 'name', 'cost_price']);

        $items = $po->exists
            ? $po->items()->get()->map(fn ($i) => [
                'sku' => $i->sku,
                'product_name' => $i->product_name,
                'quantity' => (int) $i->quantity,
                'unit_cost' => (float) $i->unit_cost,
            ])->values()
            : collect();

        return view('purchases.form', compact('po', 'suppliers', 'products', 'items'));
    }

    /** 重建明细并重算金额（编辑会清空旧明细，故需先过 editGuard） */
    private function syncItems(PurchaseOrder $po, array $items): void
    {
        $po->items()->delete();

        foreach ($items as $it) {
            $product = Product::where('sku', $it['sku'])->first();
            $po->items()->create([
                'product_id' => $product?->id,
                'sku' => $it['sku'],
                'product_name' => ($it['product_name'] ?? '') ?: ($product?->name ?? $it['sku']),
                'quantity' => $it['quantity'],
                'unit_cost' => $it['unit_cost'],
                'received_qty' => 0,
            ]);
        }

        $po->recalcTotal();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
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
    }

    private function nextPoNo(): string
    {
        return 'PO' . now()->format('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 4));
    }
}
