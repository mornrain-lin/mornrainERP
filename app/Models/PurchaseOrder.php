<?php

namespace App\Models;

use App\Enums\POStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_no', 'supplier_id', 'status', 'order_date', 'expected_at',
        'received_at', 'total_amount', 'created_by', 'remark',
    ];

    protected $casts = [
        'status' => POStatus::class,
        'order_date' => 'date',
        'expected_at' => 'date',
        'received_at' => 'date',
        'total_amount' => 'float',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** 重新计算并落库总金额（明细行合计） */
    public function recalcTotal(): void
    {
        $this->total_amount = $this->items()->sum('line_total');
        $this->save();
    }
}
