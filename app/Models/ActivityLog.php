<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 操作审计日志
 *
 * 记录关键业务动作（谁、何时、对什么、做了什么），便于追溯与责任划分。
 * 只增不改：日志本身不提供编辑入口。
 */
class ActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'subject_type', 'subject_id', 'description', 'ip', 'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 动作中文名 */
    public function actionLabel(): string
    {
        return self::actions()[$this->action] ?? $this->action;
    }

    /** 动作分组（用于筛选下拉分组） */
    public function group(): string
    {
        return str_contains($this->action, '.') ? explode('.', $this->action)[0] : 'other';
    }

    /** 被操作对象的简短标识，如 订单 ORD-001 */
    public function subjectLabel(): ?string
    {
        if (! $this->subject_type || ! $this->subject_id) {
            return null;
        }

        $model = class_exists($this->subject_type) ? $this->subject_type::find($this->subject_id) : null;

        if (! $model) {
            return class_basename($this->subject_type) . '#' . $this->subject_id;
        }

        return match (true) {
            $model instanceof Order => '订单 ' . $model->order_no,
            $model instanceof PurchaseOrder => '采购单 ' . $model->po_no,
            $model instanceof Product => 'SKU ' . $model->sku,
            $model instanceof Supplier => '供应商 ' . $model->name,
            $model instanceof Shop => '店铺 ' . $model->name,
            $model instanceof User => '账号 ' . $model->email,
            default => class_basename($model) . '#' . $model->getKey(),
        };
    }

    /** 全量动作字典（审计页筛选与展示共用） */
    public static function actions(): array
    {
        return [
            // 登录与账号
            'auth.login' => '登录成功',
            'auth.login_failed' => '登录失败',
            'auth.logout' => '退出登录',
            'auth.password' => '修改密码',
            // 订单
            'order.created' => '创建订单',
            'order.updated' => '修改订单',
            'order.deleted' => '删除订单',
            'order.shipped' => '订单发货',
            'order.status' => '订单状态流转',
            'order.imported' => '导入订单',
            // 采购
            'purchase.created' => '创建采购单',
            'purchase.updated' => '修改采购单',
            'purchase.deleted' => '删除采购单',
            'purchase.placed' => '采购单下单',
            'purchase.received' => '采购收货入库',
            'purchase.cancelled' => '取消采购单',
            // 库存
            'inventory.adjusted' => '库存调整',
            // 基础资料
            'supplier.created' => '新增供应商',
            'supplier.updated' => '修改供应商',
            'supplier.deleted' => '删除供应商',
            'supplier.toggled' => '启用/停用供应商',
            'product.created' => '新增商品',
            'product.updated' => '修改商品',
            'product.deleted' => '删除商品',
            'shop.created' => '新增店铺',
            'shop.updated' => '修改店铺',
            'shop.deleted' => '删除店铺',
            // 账号管理
            'user.created' => '新建账号',
            'user.toggled' => '启用/停用账号',
            'user.password' => '重置他人密码',
            'user.deleted' => '删除账号',
            // 平台对接
            'sync.config' => '保存对接凭证',
            'sync.run' => '执行平台拉单',
        ];
    }
}
