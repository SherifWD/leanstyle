<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Tymon\JWTAuth\Contracts\JWTSubject;
class Customer extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;
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
    protected $guarded = [];

    public function addresses() { return $this->hasMany(CustomerAddress::class); }
public function orders()    { return $this->hasMany(Order::class); }

}
