<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RemoteFileService extends Model
{
    protected $table = 'remote_file_services';

    protected $guarded = [];

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

    /* Legacy aliases */
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

    /**
     * Unified: allowed_extensions (canonical)
     * + legacy aliases for older UI code paths.
     */
    public function getAllowExtensionsAttribute($value = null)
    {
        return $this->allowed_extensions ?? null;
    }

    public function getAllowExtensionAttribute($value = null)
    {
        return $this->allowed_extensions ?? null;
    }
}