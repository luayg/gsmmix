<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceGroupPrice extends Model
{
    protected $table = 'service_group_prices';

    protected $fillable = [
        'service_id',
        'service_type',
        'group_id',
        'price',
        'discount',
        'discount_type',
    ];

    public function group()
    {
        return $this->belongsTo(ServiceGroup::class, 'group_id');
    }
}
