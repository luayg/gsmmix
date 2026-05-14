<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocalReply extends Model
{
    protected $fillable = [
        'local_source_id',
        'device_based',
        'device_identifier',
        'reply',
        'used_by_product_order_id',
        'used_at',
        'expires_at',
    ];

    protected $casts = [
        'device_based' => 'boolean',
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function source()
    {
        return $this->belongsTo(LocalSource::class, 'local_source_id');
    }

    public function productOrder()
    {
        return $this->belongsTo(ProductOrder::class, 'used_by_product_order_id');
    }

    public function linkedProductOrders()
    {
        return $this->hasMany(ProductOrder::class, 'local_reply_id');
    }

    public function getUsageLabelAttribute(): string
    {
        return $this->used_by_product_order_id
            ? 'Product order: ' . $this->used_by_product_order_id
            : 'None';
    }
}
