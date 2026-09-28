@extends('layouts.app')

@section('title', '平台对接')
@section('desc', '配置店铺 OpenAPI 凭证后即可自动拉单，也可在命令行用 php artisan orders:sync --all 定时执行')

@section('actions')
    <form method="post" action="{{ route('sync.run-all') }}" onsubmit="return confirm('确认同步所有已启用店铺？')">
        @csrf
        <button class="btn btn-primary" type="submit">⇄ 同步全部</button>
    </form>
@endsection

@section('content')
    <div class="kpi-grid">
        <div class="kpi kpi-accent">
            <div class="kpi-label">已配置对接</div>
            <div class="kpi-value">{{ $shops->filter(fn($s) => $s->isApiConfigured())->count() }} <span class="muted" style="font-size:14px">/ {{ $shops->count() }}</span></div>
            <div class="kpi-foot">店铺总数 {{ $shops->count() }} 个</div>
        </div>
        <div class="kpi kpi-accent green">
            <div class="kpi-label">已开启自动拉单</div>
            <div class="kpi-value">{{ $shops->filter(fn($s) => $s->sync_enabled)->count() }}</div>
            <div class="kpi-foot">开启后会被定时命令扫描</div>
        </div>
        <div class="kpi kpi-accent amber">
            <div class="kpi-label">最近一次同步</div>
            <div class="kpi-value small">
                @if ($logs->isNotEmpty())
                    {{ $logs->first()->created_at->format('m-d H:i') }}
                @else
                    —
                @endif
            </div>
            <div class="kpi-foot">
                {{ $logs->isNotEmpty() ? ($logs->first()->isSuccess() ? '成功' : '失败') : '暂无同步记录' }}
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">店铺对接状态</h2>
            <span class="muted" style="font-size:12px">按「立即同步」可手动拉取最近 3 天订单</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>店铺</th><th>连接器</th><th>接口地址</th><th>自动拉单</th>
                        <th>上次同步</th><th>上次结果</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($shops as $s)
                        <tr>
                            <td class="strong">
                                {{ $s->name }}
                                <div class="muted" style="font-size:11px">{{ $s->platform?->name }} · {{ $s->region ?? '—' }}</div>
                            </td>
                            <td class="muted">{{ $connectors[$s->id] ?? '—' }}</td>
                            <td class="mono">{{ $s->api_base ?: '未配置' }}</td>
                            <td>
                                @if ($s->sync_enabled)
                                    <span class="badge badge-success">已开启</span>
                                @else
                                    <span class="badge badge-muted">未开启</span>
                                @endif
                            </td>
                            <td class="nowrap muted">{{ $s->last_synced_at?->format('m-d H:i') ?? '从未同步' }}</td>
                            <td>
                                @if ($s->last_sync_status === 'success')
                                    <span class="badge badge-success">成功</span>
                                @elseif ($s->last_sync_status === 'failed')
                                    <span class="badge badge-danger">失败</span>
                                @else
                                    <span class="badge badge-muted">—</span>
                                @endif
                                @if ($s->last_sync_message)
                                    <div class="muted" style="font-size:11px">{{ $s->last_sync_message }}</div>
                                @endif
                            </td>
                            <td class="nowrap">
                                <form method="post" action="{{ route('sync.run', $s) }}" style="display:inline"
                                      onsubmit="return confirm('确认同步「{{ $s->name }}」最近 3 天订单？')">
                                    @csrf
                                    <button class="btn btn-sm" type="submit" @disabled(! $s->isApiConfigured())>立即同步</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty"><div class="big">⇄</div>还没有店铺，请先到店铺管理创建</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @foreach ($shops as $s)
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">{{ $s->name }} · 凭证配置</h2>
                <span class="muted" style="font-size:12px">
                    AppSecret 加密存储，留空表示不修改
                    @if ($s->isApiConfigured()) · <span class="badge badge-success">已配置</span>@endif
                </span>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('sync.config', $s) }}">
                    @csrf
                    @method('PUT')
                    <div class="form-grid">
                        <div class="field">
                            <label for="api_base_{{ $s->id }}">API 根地址</label>
                            <input type="text" id="api_base_{{ $s->id }}" name="api_base" value="{{ $s->api_base }}" placeholder="https://openapi.example.com">
                        </div>
                        <div class="field">
                            <label for="app_key_{{ $s->id }}">AppKey / ClientId</label>
                            <input type="text" id="app_key_{{ $s->id }}" name="app_key" value="{{ $s->app_key }}" autocomplete="off">
                        </div>
                        <div class="field">
                            <label for="app_secret_{{ $s->id }}">AppSecret</label>
                            <input type="password" id="app_secret_{{ $s->id }}" name="app_secret" placeholder="留空则不修改" autocomplete="new-password">
                        </div>
                        <div class="field">
                            <label for="sync_enabled_{{ $s->id }}">自动拉单</label>
                            <select id="sync_enabled_{{ $s->id }}" name="sync_enabled">
                                <option value="0" @selected(! $s->sync_enabled)>关闭</option>
                                <option value="1" @selected($s->sync_enabled)>开启</option>
                            </select>
                        </div>
                    </div>
                    <div class="btn-row" style="margin-top:14px">
                        <button class="btn btn-primary" type="submit">保存配置</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach

    <div class="card">
        <div class="card-head"><h2 class="card-title">同步日志</h2><span class="muted" style="font-size:12px">最近 20 条</span></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>时间</th><th>店铺</th><th>触发</th><th>时间窗</th>
                        <th class="num">拉取</th><th class="num">新建</th><th class="num">更新</th>
                        <th class="num">耗时</th><th>结果</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="nowrap mono">{{ $log->created_at->format('m-d H:i:s') }}</td>
                            <td>{{ $log->shop?->name ?? '—' }}</td>
                            <td class="muted">{{ $log->trigger === 'command' ? '命令行' : '手动' }}</td>
                            <td class="nowrap muted" style="font-size:12px">
                                {{ $log->range_from?->format('m-d H:i') }} → {{ $log->range_to?->format('m-d H:i') }}
                            </td>
                            <td class="num">{{ $log->fetched }}</td>
                            <td class="num">{{ $log->created }}</td>
                            <td class="num">{{ $log->updated }}</td>
                            <td class="num muted">{{ $log->duration_ms }} ms</td>
                            <td>
                                <span class="badge {{ $log->isSuccess() ? 'badge-success' : 'badge-danger' }}">
                                    {{ $log->isSuccess() ? '成功' : '失败' }}
                                </span>
                                @if ($log->message)
                                    <div class="muted" style="font-size:11px">{{ Str::limit($log->message, 60) }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty"><div class="big">⇄</div>还没有同步记录</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">接口契约（通用 REST 连接器）</h2></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field">
                    <div class="hint">
                        请求：<span class="mono">GET {api_base}/orders?updated_from=&amp;updated_to=&amp;page=&amp;page_size=</span><br>
                        鉴权头：<span class="mono">X-Api-Key / X-Timestamp / X-Signature</span><br>
                        签名：<span class="mono">HMAC-SHA256(app_key + "\n" + timestamp + "\n" + "/orders", app_secret)</span>
                    </div>
                </div>
                <div class="field">
                    <div class="hint">
                        响应：<span class="mono">{"data":[{ "order_no":"", "buyer":{"name":""}, "goods_amount":0, "items":[{"sku":"","quantity":1,"price":0}] }], "has_more":false}</span><br>
                        订单号、商品金额、明细 SKU 为必填，其余字段缺失按 0 / null 处理。<br>
                        定时拉单：<span class="mono">php artisan orders:sync --all</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
