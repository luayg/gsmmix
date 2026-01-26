<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RemoteImeiService extends Model
{
    protected $table = 'remote_imei_services';

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

        'network',
        'mobile',
        'provider',
        'pin',
        'kbh',
        'mep',
        'prd',
        'type',
        'locks',
        'reference',
        'udid',
        'serial',
        'secro',

        'credit_groups',
        'additional_fields',
        'additional_data',
        'params',
    ];

    protected $casts = [
        'price' => 'decimal:4',

        'network' => 'boolean',
        'mobile' => 'boolean',
        'provider' => 'boolean',
        'pin' => 'boolean',
        'kbh' => 'boolean',
        'mep' => 'boolean',
        'prd' => 'boolean',
        'type' => 'boolean',
        'locks' => 'boolean',
        'reference' => 'boolean',
        'udid' => 'boolean',
        'serial' => 'boolean',
        'secro' => 'boolean',

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
