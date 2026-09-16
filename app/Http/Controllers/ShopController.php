<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use App\Models\Shop;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index()
    {
        $shops = Shop::with('platform')
            ->withCount('orders')
            ->orderBy('platform_id')
            ->paginate(12);

        return view('shops.index', [
            'shops' => $shops,
            'platforms' => Platform::orderBy('id')->get(),
        ]);
    }

    public function create()
    {
        return view('shops.form', [
            'shop' => new Shop(['currency' => 'CNY', 'exchange_rate' => 1, 'is_active' => true]),
            'platforms' => Platform::orderBy('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Shop::create($data);

        return redirect()->route('shops.index')->with('ok', '店铺已创建');
    }

    public function edit(Shop $shop)
    {
        return view('shops.form', [
            'shop' => $shop,
            'platforms' => Platform::orderBy('id')->get(),
        ]);
    }

    public function update(Request $request, Shop $shop)
    {
        $shop->update($this->validated($request));

        return redirect()->route('shops.index')->with('ok', '店铺已更新');
    }

    public function destroy(Shop $shop)
    {
        if ($shop->orders()->exists()) {
            return back()->with('err', '该店铺下已有订单，无法删除（可先停用）');
        }
        $shop->delete();

        return redirect()->route('shops.index')->with('ok', '店铺已删除');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'platform_id' => 'required|exists:platforms,id',
            'name' => 'required|string|max:96',
            'seller_account' => 'nullable|string|max:96',
            'region' => 'nullable|string|max:16',
            'currency' => 'required|string|max:8',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'is_active' => 'nullable|boolean',
            'remark' => 'nullable|string',
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
