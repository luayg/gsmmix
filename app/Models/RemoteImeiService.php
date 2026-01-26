<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RemoteImeiService extends Model
{
    protected $table = 'remote_imei_services';

    protected $guarded = [];

    protected $casts = [
        'price' => 'decimal:4',
        'credit_groups' => 'array',
        'additional_fields' => 'array',
        'additional_data' => 'array',
        'params' => 'array',

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
    ];

    public function provider()
    {
        return $this->belongsTo(ApiProvider::class, 'api_provider_id');
    }

    /* =========================================================
     | Legacy aliases for existing Blade/JS (NO UI changes)
     |========================================================= */

    // Group -> group_name
    public function getGroupAttribute($value = null)
    {
        return $this->group_name;
    }

    // service_id -> remote_id
    public function getServiceIdAttribute($value = null)
    {
        return $this->remote_id;
    }

    // service_name -> name
    public function getServiceNameAttribute($value = null)
    {
        return $this->name;
    }

    // credit/credits -> price
    public function getCreditAttribute($value = null)
    {
        return $this->price;
    }

    public function getCreditsAttribute($value = null)
    {
        return $this->price;
    }
}
