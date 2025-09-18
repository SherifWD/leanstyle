<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $guarded = [];

    public function category() { return $this->belongsTo(Category::class); }

    public function getImagePathAttribute($val)
    {
        return ($val !== null) ? asset('/'.$val) : "";
    }
}

