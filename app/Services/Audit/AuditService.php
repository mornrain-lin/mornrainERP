<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * 操作审计
 *
 * 统一记录关键动作。调用方只需给出动作名与一句话描述，
 * 操作人、IP、UA 由本服务自动补齐（命令行下无 request 时留空）。
 */
class AuditService
{
    /**
     * @param  string       $action      动作名，如 order.created
     * @param  string|null  $description 人类可读描述
     * @param  Model|null   $subject     被操作对象
     * @param  int|null     $userId      操作人，缺省取当前登录用户
     */
    public function log(string $action, ?string $description = null, ?Model $subject = null, ?int $userId = null): ActivityLog
    {
        $request = request();

        return ActivityLog::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'ip' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 500) : null,
        ]);
    }
}
