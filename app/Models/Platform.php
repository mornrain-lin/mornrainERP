<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'region', 'commission_rate', 'payment_fee_rate', 'color', 'is_active',
    ];

    protected $casts = [
        'commission_rate' => 'float',
        'payment_fee_rate' => 'float',
        'is_active' => 'bool',
    ];

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
