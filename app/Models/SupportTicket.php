<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    protected $fillable = [
        'user_id','customer_id','name','phone','email','subject','message','attachments','status'
    ];

    protected $casts = [
        'attachments' => 'array',
    ];
}
