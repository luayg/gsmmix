<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RemoteServerService extends Model
{
    protected $table = 'remote_server_services';

    protected $guarded = [];

    protected $casts = [
        'additional_fields' => 'array',
        'additional_data'   => 'array',
        'params'            => 'array',
        'price'             => 'float',
        'api_provider_id'   => 'int',
    ];
}
