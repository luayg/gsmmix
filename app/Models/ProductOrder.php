<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOrder extends Model
{
    protected $fillable = [
        'status','order_price','user_id','email','comments'
    ];

    protected $casts = [];
}
