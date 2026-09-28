<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\Audit\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 供应商管理（管理员）
 *
 * 采购单的归属方。停用后不再出现在采购单新建下拉中，
 * 但历史采购单仍保留归属，避免账面对不上。
 */
class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');

        $suppliers = Supplier::query()
            ->withCount('purchaseOrders')
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                        ->orWhere('contact', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->when($status === 'active', fn ($b) => $b->where('is_active', true))
            ->when($status === 'inactive', fn ($b) => $b->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('suppliers.index', compact('suppliers', 'q', 'status'));
    }

    public function create()
    {
        return view('suppliers.form', ['supplier' => new Supplier()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $supplier = Supplier::create($this->validated($request));

        app(AuditService::class)->log('supplier.created', "新增供应商「{$supplier->name}」", $supplier);

        return redirect()->route('suppliers.index')
            ->with('ok', "供应商「{$supplier->name}」已创建");
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.form', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request, $supplier->id));

        app(AuditService::class)->log('supplier.updated', "修改供应商「{$supplier->name}」", $supplier);

        return redirect()->route('suppliers.index')
            ->with('ok', "供应商「{$supplier->name}」已保存");
    }

    /** 已关联采购单的供应商不能删除，只能停用 */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        $used = $supplier->purchaseOrders()->count();
        if ($used > 0) {
            return back()->with(
                'err',
                "「{$supplier->name}」已关联 {$used} 张采购单，不能删除；请改用「停用」以保留历史单据"
            );
        }

        $name = $supplier->name;
        $supplier->delete();

        app(AuditService::class)->log('supplier.deleted', "删除供应商「{$name}」");

        return redirect()->route('suppliers.index')->with('ok', "供应商「{$name}」已删除");
    }

    public function toggle(Supplier $supplier): RedirectResponse
    {
        $supplier->update(['is_active' => ! $supplier->is_active]);

        app(AuditService::class)->log(
            'supplier.toggled',
            "「{$supplier->name}」" . ($supplier->is_active ? '启用' : '停用'),
            $supplier
        );

        return back()->with('ok', "「{$supplier->name}」已" . ($supplier->is_active ? '启用' : '停用'));
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', $id ? "unique:suppliers,name,{$id}" : 'unique:suppliers,name'],
            'contact' => ['nullable', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'remark' => ['nullable', 'string', 'max:500'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
