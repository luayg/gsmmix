<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiProvider extends Model
{
    protected $table = 'api_providers';

    protected $fillable = [
        'name',
        'type',
        'url',
        'username',
        'api_key',
        'params',

        'sync_imei',
        'sync_server',
        'sync_file',

        'ignore_low_balance',
        'auto_sync',
        'active',
        'synced',

        'balance',

        'available_imei',
        'used_imei',
        'available_server',
        'used_server',
        'available_file',
        'used_file',
    ];

    protected $casts = [
        'params' => 'array',

        'sync_imei' => 'boolean',
        'sync_server' => 'boolean',
        'sync_file' => 'boolean',

        'ignore_low_balance' => 'boolean',
        'auto_sync' => 'boolean',
        'active' => 'boolean',
        'synced' => 'boolean',

        'balance' => 'decimal:2',
    ];

    public function remoteImeiServices()
    {
        return $this->hasMany(RemoteImeiService::class, 'api_provider_id');
    }

    public function remoteServerServices()
    {
        return $this->hasMany(RemoteServerService::class, 'api_provider_id');
    }

    public function remoteFileServices()
    {
        return $this->hasMany(RemoteFileService::class, 'api_provider_id');
    }

    /**
     * Normalize endpoint for DHRU-style APIs (DHRU / GSMHub etc).
     * If url is base domain, we append /api/index.php
     */
    public function dhruEndpoint(): string
    {
        $url = rtrim((string) $this->url, '/');
        if (str_ends_with($url, '/api/index.php')) {
            return $url;
        }
        return $url . '/api/index.php';
    }
}
