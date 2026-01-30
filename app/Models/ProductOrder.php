<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOrder extends Model
{
    protected $fillable = ['user_id','email','status','total','notes','items'];

    protected $casts = ['items' => 'array'];

    public function user(){ return $this->belongsTo(User::class, 'user_id'); }
}
