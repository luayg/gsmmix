<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ApiProvider extends Model
{
    protected $table = 'api_providers';

    protected $fillable = [
        'name','type','url','username','api_key',
        'params', // ✅ جديد
        'sync_imei','sync_server','sync_file',
        'ignore_low_balance','auto_sync','active','synced',
        'balance','available_imei','used_imei',
        'available_server','used_server','available_file','used_file',
    ];

    protected $casts = [
        'sync_imei'  => 'boolean',
        'sync_server'=> 'boolean',
        'sync_file'  => 'boolean',
        'ignore_low_balance' => 'boolean',
        'auto_sync'  => 'boolean',
        'active'     => 'boolean',
        'synced'     => 'boolean',

        // ✅ params نخزنها JSON داخل longText
        'params'     => 'string',
    ];

    /** params كـ array (راحة في الاستخدام) */
    public function getParamsJsonAttribute(): array
    {
        $raw = $this->params ?? '';
        $arr = json_decode($raw, true);
        return is_array($arr) ? $arr : [];
    }

    public function setParamsJsonAttribute($value): void
    {
        $this->attributes['params'] = json_encode($value ?: [], JSON_UNESCAPED_UNICODE);
    }

    /** Helper */
    public function param(string $key, $default = null)
    {
        $p = $this->params_json;
        return $p[$key] ?? $default;
    }

    // علاقات الخدمات المحلية
    public function imeiServices()   { return $this->hasMany(ImeiService::class,   'supplier_id'); }
    public function serverServices() { return $this->hasMany(ServerService::class, 'supplier_id'); }
    public function fileServices()   { return $this->hasMany(FileService::class,   'supplier_id'); }

    // علاقات الـRemote
    public function remoteImeiServices()   { return $this->hasMany(RemoteImeiService::class,   'api_id'); }
    public function remoteServerServices() { return $this->hasMany(RemoteServerService::class, 'api_id'); }
    public function remoteFileServices()   { return $this->hasMany(RemoteFileService::class,   'api_id'); }

    protected static function booted()
    {
        static::created(function (ApiProvider $p) {
            // ✅ لا نحصر المزامنة فقط في dhru (سنعدل المنطق لاحقًا عبر Factory)
            // حاليا: خليه مثل السابق لكن لا يمنع إضافة مزودين آخرين
            if ($p->type === 'dhru' && (int)$p->active === 1) {
                Artisan::call('dhru:sync', ['--provider' => [$p->id]]);
            }
        });

        static::deleting(function (ApiProvider $p) {
            DB::table('remote_imei_services')->where('api_id', $p->id)->delete();
            DB::table('remote_server_services')->where('api_id', $p->id)->delete();
            DB::table('remote_file_services')->where('api_id', $p->id)->delete();
        });
    }
}
