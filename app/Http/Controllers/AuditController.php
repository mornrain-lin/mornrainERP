<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * 操作审计（管理员）
 *
 * 只读：记录由各业务控制器写入，此处仅提供检索与追溯。
 */
class AuditController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $action = (string) $request->query('action', 'all');
        $userId = (string) $request->query('user_id', '');
        $days = (int) $request->query('days', 30);

        $logs = ActivityLog::with('user')
            ->when($q !== '', fn ($b) => $b->where('description', 'like', "%{$q}%"))
            ->when($action !== 'all', fn ($b) => $b->where('action', $action))
            ->when($userId !== '', fn ($b) => $b->where('user_id', (int) $userId))
            ->when($days > 0, fn ($b) => $b->where('created_at', '>=', now()->subDays($days)))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        $actions = ActivityLog::actions();

        return view('audit.index', compact('logs', 'users', 'actions', 'q', 'action', 'userId', 'days'));
    }
}
