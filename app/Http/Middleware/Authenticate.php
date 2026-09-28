<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /** 未登录统一跳登录页，并在会话里记住原本想去的地址 */
    protected function redirectTo(Request $request): ?string
    {
        if (! $request->expectsJson()) {
            $request->session()?->put('url.intended', $request->fullUrl());

            return route('login');
        }

        return null;
    }
}
