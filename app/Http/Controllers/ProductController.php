<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $kw = $request->input('q');
                $q->where(fn ($s) => $s->where('sku', 'like', "%{$kw}%")
                    ->orWhere('name', 'like', "%{$kw}%")
                    ->orWhere('category', 'like', "%{$kw}%"));
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'q' => $request->input('q'),
        ]);
    }

    public function create()
    {
        return view('products.form', [
            'product' => new Product(['cost_price' => 0, 'weight_g' => 0, 'is_active' => true]),
        ]);
    }

    public function store(Request $request)
    {
        Product::create($this->validated($request));

        return redirect()->route('products.index')->with('ok', '商品已创建');
    }

    public function edit(Product $product)
    {
        return view('products.form', ['product' => $product]);
    }

    public function update(Request $request, Product $product)
    {
        $product->update($this->validated($request, $product));

        return redirect()->route('products.index')->with('ok', '商品已更新');
    }

    public function destroy(Product $product)
    {
        if ($product->orderItems()->exists()) {
            return back()->with('err', '该 SKU 已产生订单明细，无法删除（可先停用）');
        }
        $product->delete();

        return redirect()->route('products.index')->with('ok', '商品已删除');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'sku' => 'required|string|max:64|unique:products,sku' . ($product ? ',' . $product->id : ''),
            'name' => 'required|string|max:160',
            'category' => 'nullable|string|max:64',
            'cost_price' => 'required|numeric|min:0',
            'weight_g' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
