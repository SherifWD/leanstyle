<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $guarded = [];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function assignment()
    {
        return $this->hasOne(OrderAssignment::class);
    }

    public function driver()
    {
        return $this->hasOneThrough(User::class, OrderAssignment::class, 'order_id', 'id', 'id', 'driver_id');
    }

    public function couponRedemptions()
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function cashLedgerEntries()
    {
        return $this->hasMany(DriverCashLedger::class);
    }

    public function address()
    {
        return $this->belongsTo(CustomerAddress::class, 'address_id');
    }

    public static function statusOptions(): array
    {
        $seed = [
            'pending',
            'preparing',
            'ready',
            'assigned',
            'started',
            'picked',
            'out_for_delivery',
            'delivered',
            'rejected',
            'cancelled',
        ];

        $current = static::query()
            ->whereNotNull('status')
            ->distinct()
            ->pluck('status');

        $historyFrom = OrderStatusHistory::query()
            ->whereNotNull('from_status')
            ->distinct()
            ->pluck('from_status');

        $historyTo = OrderStatusHistory::query()
            ->whereNotNull('to_status')
            ->distinct()
            ->pluck('to_status');

        return collect($seed)
            ->merge($current)
            ->merge($historyFrom)
            ->merge($historyTo)
            ->filter()
            ->unique()
            ->values()
            ->mapWithKeys(function ($status) {
                $label = Str::of($status)->replace('_', ' ')->headline();

                return [$status => (string) $label];
            })
            ->toArray();
    }

    public static function paymentMethodOptions(): array
    {
        $methods = static::query()
            ->select('payment_method')
            ->distinct()
            ->pluck('payment_method', 'payment_method')
            ->filter()
            ->map(fn ($value) => Str::of($value)->replace('_', ' ')->headline())
            ->toArray();

        if (empty($methods)) {
            $methods['cod'] = 'Cod';
        }

        return $methods;
    }
}
