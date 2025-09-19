<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements FilamentUser, JWTSubject
{
    use HasFactory, Notifiable;

    /** -------- Filament access -------- */
    public function canAccessPanel(Panel $panel): bool
    {
        // Admins & shop owners can use the dashboard; drivers only use the mobile app.
        return in_array($this->role, ['admin', 'shop_owner'], true) && ! $this->is_blocked;
    }

    public function getFilamentName(): string
    {
        return $this->name ?? ($this->email ?? $this->phone ?? 'User');
    }

    /** -------- JWTSubject required methods -------- */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        // Add anything you want embedded in the token:
        return [
            'role'     => $this->role,
            'store_id' => $this->store_id,
        ];
    }

    /** -------- Eloquent config -------- */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'store_id',
        'is_blocked',
        'is_available',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            // If you keep this, DO NOT Hash::make() again in your controller.
            'password' => 'hashed',
            'is_blocked' => 'boolean',
            'is_available' => 'boolean',
        ];
    }

    /** -------- Relationships -------- */
    public function ownedStore()        { return $this->hasOne(Store::class, 'owner_id'); } // shop owner
    public function ownedStores()       { return $this->hasMany(Store::class, 'owner_id'); }
    public function store()             { return $this->belongsTo(Store::class); }          // optional store link
    public function orderAssignments()  { return $this->hasMany(OrderAssignment::class, 'driver_id'); }
    public function assignedOrders()    { return $this->hasManyThrough(Order::class, OrderAssignment::class, 'driver_id', 'id', 'id', 'order_id'); }
    public function statusChanges()     { return $this->hasMany(OrderStatusHistory::class, 'changed_by'); }
    public function driverAvailabilities(){ return $this->hasMany(DriverAvailability::class, 'driver_id'); }
    public function cashLedgers()       { return $this->hasMany(DriverCashLedger::class, 'driver_id'); }
    public function remittances()       { return $this->hasMany(DriverRemittance::class, 'driver_id'); }
    public function remittancesReceived(){ return $this->hasMany(DriverRemittance::class, 'received_by'); } // admin
    public function carts()             { return $this->hasMany(Cart::class); }
    public function productViews()      { return $this->hasMany(ProductView::class); }
}
