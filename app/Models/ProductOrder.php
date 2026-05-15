<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOrder extends Model
{
    protected $fillable = [
        'status',
        'order_price',
        'user_id',
        'local_source_id',
        'local_reply_id',
        'email',
        'comments',
    ];

    protected $casts = [];

    public function localSource()
    {
        return $this->belongsTo(LocalSource::class, 'local_source_id');
    }

    public function localReply()
    {
        return $this->belongsTo(LocalReply::class, 'local_reply_id');
    }
}