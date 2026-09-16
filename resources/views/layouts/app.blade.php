<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '概览') · moringrainERP</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-logo">MR</div>
            <div class="brand-text">
                <span class="brand-name">moringrainERP</span>
                <span class="brand-sub">轻量级跨境 ERP</span>
            </div>
        </div>

        <nav class="nav">
            <div class="nav-group">
                <div class="nav-title">经营</div>
                <a class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <span class="ico">◪</span> 概览
                </a>
                <a class="nav-item {{ request()->routeIs('orders.index') ? 'active' : '' }}" href="{{ route('orders.index') }}">
                    <span class="ico">▤</span> 订单管理
                </a>
                <a class="nav-item {{ request()->routeIs('orders.import.form') ? 'active' : '' }}" href="{{ route('orders.import.form') }}">
                    <span class="ico">⇪</span> 订单导入
                </a>
            </div>
            <div class="nav-group">
                <div class="nav-title">分析</div>
                <a class="nav-item {{ request()->routeIs('reports.profit') ? 'active' : '' }}" href="{{ route('reports.profit') }}">
                    <span class="ico">◈</span> 利润报表
                </a>
            </div>
            <div class="nav-group">
                <div class="nav-title">基础资料</div>
                <a class="nav-item {{ request()->routeIs('shops.*') ? 'active' : '' }}" href="{{ route('shops.index') }}">
                    <span class="ico">⛬</span> 店铺管理
                </a>
                <a class="nav-item {{ request()->routeIs('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}">
                    <span class="ico">▣</span> 商品 / SKU
                </a>
            </div>
        </nav>

        <div class="sidebar-foot">
            MVP v0.1 · 订单管理<br>
            © {{ date('Y') }} moringrain
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <h1 class="page-title">@yield('title', '概览')</h1>
                @hasSection('desc')
                    <div class="page-desc">@yield('desc')</div>
                @endif
            </div>
            <div class="topbar-right">@yield('actions')</div>
        </header>

        <div class="content">
            @if (session('ok'))
                <div class="alert alert-ok"><span>✓</span><span>{{ session('ok') }}</span></div>
            @endif
            @if (session('err'))
                <div class="alert alert-err"><span>!</span><span>{{ session('err') }}</span></div>
            @endif
            @if ($errors->any())
                <div class="alert alert-err">
                    <span>!</span>
                    <div>
                        <div class="strong">请检查以下问题：</div>
                        <ul class="err-list">
                            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
