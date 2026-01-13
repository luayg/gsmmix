<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImeiService extends Model
{
    protected $table = 'imei_services';
    public $timestamps = true;

    protected $fillable = [
        'icon','alias','name','time','info','cost','profit','profit_type','main_field','params',
        'active','allow_bulk','allow_duplicates','reply_with_latest','allow_report','allow_report_time',
        'allow_cancel','allow_cancel_time','use_remote_cost','use_remote_price','stop_on_api_change',
        'needs_approval','reply_expiration','expiration_text','type','group_id','source','remote_id',
        'supplier_id','local_source_id','device_based','reject_on_missing_reply','ordering',
    ];

    protected $casts = [
        'cost' => 'float',
        'profit' => 'float',
        'profit_type' => 'int',

        'active' => 'bool',
        'allow_bulk' => 'bool',
        'allow_duplicates' => 'bool',
        'reply_with_latest' => 'bool',
        'allow_report' => 'bool',
        'allow_report_time' => 'int',
        'allow_cancel' => 'bool',
        'allow_cancel_time' => 'int',
        'use_remote_cost' => 'bool',
        'use_remote_price' => 'bool',
        'stop_on_api_change' => 'bool',
        'needs_approval' => 'bool',
        'reply_expiration' => 'int',

        'group_id' => 'int',
        'source' => 'int',

        // إذا عندك remote_id أحياناً varchar في DB غيّرها إلى 'string'
        'remote_id' => 'int',
        'supplier_id' => 'int',
        'local_source_id' => 'int',

        'device_based' => 'bool',
        'reject_on_missing_reply' => 'bool',
        'ordering' => 'int',
    ];

    /**
     * ⚠️ مهم:
     * source ليس api_provider.
     * source عندك = Manual / API (مثلاً 1/2)
     * لذلك هذه العلاقة كانت خاطئة عندك:
     * return $this->belongsTo(ApiProvider::class,'source');
     */

    // ✅ المزوّد الحقيقي للخدمة القادمة من API (جدول api_providers)
    public function supplier()
    {
        return $this->belongsTo(ApiProvider::class, 'supplier_id');
    }

    // لو عندك كود قديم يستدعي api() خليه شغال لكن خلّيه يرجع supplier الصحيح
    public function api()
    {
        return $this->supplier();
    }

    public function group()
    {
        return $this->belongsTo(ServiceGroup::class, 'group_id');
    }

    public function groupPrices()
    {
        return $this->hasMany(\App\Models\ServiceGroupPrice::class, 'service_id')
            ->where('service_type', 'imei');
    }

    /* ============================================================
     *  ✅ Helpers لفك JSON المخزن داخل name/time/info
     *  ويعطيك خصائص جاهزة للعرض في الجداول والواجهات
     * ============================================================ */

    public function getNameJsonAttribute(): array
    {
        return $this->decodeJsonField($this->attributes['name'] ?? null);
    }

    public function getTimeJsonAttribute(): array
    {
        return $this->decodeJsonField($this->attributes['time'] ?? null);
    }

    public function getInfoJsonAttribute(): array
    {
        return $this->decodeJsonField($this->attributes['info'] ?? null);
    }

    // ✅ نص جاهز للعرض (أفضل قيمة بين fallback/en)
    public function getNameTextAttribute(): string
    {
        $j = $this->name_json;
        return (string) ($j['fallback'] ?? ($j['en'] ?? ''));
    }

    public function getTimeTextAttribute(): string
    {
        $j = $this->time_json;
        return (string) ($j['fallback'] ?? ($j['en'] ?? ''));
    }

    public function getInfoTextAttribute(): string
    {
        $j = $this->info_json;
        return (string) ($j['fallback'] ?? ($j['en'] ?? ''));
    }

    private function decodeJsonField($value): array
    {
        if ($value === null || $value === '') {
            return ['en' => '', 'fallback' => ''];
        }

        // إذا كانت أصلاً Array
        if (is_array($value)) {
            return array_merge(['en' => '', 'fallback' => ''], $value);
        }

        // إذا كانت String JSON
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_merge(['en' => '', 'fallback' => ''], $decoded);
            }

            // نص عادي وليس JSON
            return ['en' => $value, 'fallback' => $value];
        }

        return ['en' => '', 'fallback' => ''];
    }
}
