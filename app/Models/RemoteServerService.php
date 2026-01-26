<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RemoteServerService extends Model
{
    protected $table = 'remote_server_services';

    protected $fillable = [
        'api_provider_id',
        'remote_id',
        'name',
        'group_name',
        'price',
        'time',
        'info',
        'min_qty',
        'max_qty',

        'credit_groups',
        'additional_fields',
        'additional_data',
        'params',
    ];

    protected $casts = [
        'price' => 'decimal:4',
        'credit_groups' => 'array',
        'additional_fields' => 'array',
        'additional_data' => 'array',
        'params' => 'array',
    ];

    public function provider()
    {
        return $this->belongsTo(ApiProvider::class, 'api_provider_id');
    }
}
