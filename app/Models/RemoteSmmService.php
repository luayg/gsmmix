<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RemoteSmmService extends Model
{
    protected $table = 'remote_smm_services';

    protected $guarded = [];

    protected $casts = [
        'additional_fields' => 'array',
        'additional_data'   => 'array',
        'params'            => 'array',
        'price'             => 'float',
        'api_provider_id'   => 'int',
        'min'               => 'int',
        'max'               => 'int',
        'refill'            => 'boolean',
        'cancel'            => 'boolean',
    ];

    public function provider()
    {
        return $this->belongsTo(ApiProvider::class, 'api_provider_id');
    }

    public function getGroupAttribute($value = null)
    {
        return $this->group_name;
    }

    public function getServiceIdAttribute($value = null)
    {
        return $this->remote_id;
    }

    public function getServiceNameAttribute($value = null)
    {
        return $this->name;
    }

    public function getCreditAttribute($value = null)
    {
        return $this->price;
    }

    public function getCreditsAttribute($value = null)
    {
        return $this->price;
    }
}