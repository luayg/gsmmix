<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOrder extends Model
{
    protected $fillable = [
        'product_id',
        'service_order_type',
        'service_order_id',
        'status',
        'order_price',
        'user_id',
        'local_source_id',
        'local_reply_id',
        'email',
        'comments',
        'request_uid',
        'device',
        'request',
        'response',
        'replied_at',
    ];

    protected $casts = [
        'order_price' => 'decimal:2',
        'request' => 'array',
        'replied_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function localSource()
    {
        return $this->belongsTo(LocalSource::class, 'local_source_id');
    }

    public function localReply()
    {
        return $this->belongsTo(LocalReply::class, 'local_reply_id');
    }
}
