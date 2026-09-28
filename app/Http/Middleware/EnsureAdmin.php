<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 管理员闸门：店铺/商品/平台对接/账号管理等基础资料只放行 admin
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null && $user->isAdmin(), 403, '仅管理员可执行该操作');

        return $next($request);
    }
}
