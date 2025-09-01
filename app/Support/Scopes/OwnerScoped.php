<?php
namespace App\Support\Scopes;

use Illuminate\Database\Eloquent\Builder;

class OwnerScoped
{
    public static function apply(Builder $query, string $column = 'store_id'): Builder
    {
        $user = auth()->user();
        if ($user && $user->role === 'shop_owner' && $user->store_id) {
            $query->where($column, $user->store_id);
        }
        return $query;
    }
}