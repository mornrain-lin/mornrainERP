@extends('layouts.app')

@section('title', '订单导入')
@section('desc', '上传平台导出的 CSV，系统自动归集订单、明细与成本，同一订单号的多行自动合并')

@section('actions')
    <a class="btn" href="{{ route('orders.index') }}">← 返回列表</a>
@endsection

@section('content')
    <div class="grid grid-2">
        <div class="card">
            <div class="card-head"><h2 class="card-title">上传 CSV</h2></div>
            <div class="card-body">
                <form method="post" action="{{ route('orders.import') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="field" style="margin-bottom:14px">
                        <label>选择文件（.csv / .txt，UTF-8）</label>
                        <input type="file" name="file" accept=".csv,.txt" required>
                        <span class="hint">建议从平台后台导出订单报表后，按下方列名整理</span>
                    </div>
                    <button class="btn btn-primary" type="submit">开始导入</button>
                </form>

                <div style="margin-top:20px">
                    <div class="section-title">处理规则</div>
                    <ol class="muted" style="font-size:12.5px;padding-left:18px;line-height:1.9">
                        <li>以 <span class="mono">order_no</span> 为唯一键，重复订单自动跳过</li>
                        <li>同一 <span class="mono">order_no</span> 出现多行 → 合并为一张订单 + 多条商品明细</li>
                        <li>平台按 <span class="mono">platform_code</span> 匹配；店铺不存在时自动创建</li>
                        <li>佣金 / 手续费留空时，按平台默认费率自动计算</li>
                        <li>SKU 若能匹配到商品库，自动带出采购成本</li>
                    </ol>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h2 class="card-title">CSV 列名参考</h2>
                <span class="muted" style="font-size:12px">可直接复制下方示例</span>
            </div>
            <div class="card-body">
                <pre class="mono" style="background:#f7f9fc;border:1px solid var(--line);border-radius:8px;padding:12px;overflow-x:auto;font-size:11.5px;line-height:1.7;white-space:pre">{{ $sample }}</pre>
            </div>
        </div>
    </div>
@endsection
