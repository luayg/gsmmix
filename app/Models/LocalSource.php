<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocalSource extends Model
{
    protected $fillable = [
        'name',
    ];

    public function replies()
    {
        return $this->hasMany(LocalReply::class);
    }

    public function productOrders()
    {
        return $this->hasMany(ProductOrder::class);
    }
}