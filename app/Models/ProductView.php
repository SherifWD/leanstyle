<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductView extends Model
{
    protected $table = 'product_views';
    protected $fillable = ['user_id','product_id','viewed_at'];
    protected $casts = [
        'viewed_at' => 'datetime',
    ];
    // Use timestamps, but map created_at to viewed_at and disable updated_at
    public $timestamps = true;
    const CREATED_AT = 'viewed_at';
    const UPDATED_AT = null;

    public function user()    { return $this->belongsTo(User::class); }
public function product() { return $this->belongsTo(Product::class); }

}
