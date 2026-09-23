<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'minimum_order_amount',
        'maximum_discount',
        'start_date',
        'end_date',
        'usage_limit',
        'per_customer_limit',
        'active',
    ];

    protected $casts = [
        'discount_value' => 'float',
        'minimum_order_amount' => 'float',
        'maximum_discount' => 'float',
        'active' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }
}
