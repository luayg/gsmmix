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
    ];

    // علاقات الخدمات المحلية (لو كنت تستخدمها بمكان آخر)
    public function imeiServices()   { return $this->hasMany(ImeiService::class,   'supplier_id'); }
    public function serverServices() { return $this->hasMany(ServerService::class, 'supplier_id'); }
    public function fileServices()   { return $this->hasMany(FileService::class,   'supplier_id'); }

    // علاقات جداول الـRemote (التي نزامنها من DHRU)
    public function remoteImeiServices()   { return $this->hasMany(RemoteImeiService::class,   'api_id'); }
    public function remoteServerServices() { return $this->hasMany(RemoteServerService::class, 'api_id'); }
    public function remoteFileServices()   { return $this->hasMany(RemoteFileService::class,   'api_id'); }

    protected static function booted()
    {
        // تزامن تلقائي عند إنشاء مزوّد جديد
        static::created(function (ApiProvider $p) {
            // فقط لمزوّدات DHRU النشطة
            if ($p->type === 'dhru' && (int)$p->active === 1) {
                // شغّل الأمر للتزامن الأولي
                Artisan::call('dhru:sync', ['--provider' => [$p->id]]);
            }
        });

        // تنظيف تلقائي عند الحذف (بالإضافة إلى FK CASCADE إن وُجد)
        static::deleting(function (ApiProvider $p) {
            // هذا احتياطي إذا لم تكن مفاتيح FK مضافة بعد
            DB::table('remote_imei_services')->where('api_id', $p->id)->delete();
            DB::table('remote_server_services')->where('api_id', $p->id)->delete();
            DB::table('remote_file_services')->where('api_id', $p->id)->delete();
        });
    }
}
