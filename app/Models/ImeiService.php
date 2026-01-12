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
  'remote_id' => 'int',
  'supplier_id' => 'int',
  'local_source_id' => 'int',
  'device_based' => 'bool',
  'reject_on_missing_reply' => 'bool',
  'ordering' => 'int',
];
public function api()
{
    // مصدر الخدمة الأساسي (الذي أحيانًا يخزن في العمود source)
    return $this->belongsTo(ApiProvider::class, 'source');
}



public function groupPrices()
{
    return $this->hasMany(\App\Models\ServiceGroupPrice::class, 'service_id')
                ->where('service_type', 'imei');
}

public function supplier()
{
    // المقصود بها api_providers (التي تأتي من جدول api_providers)
    return $this->belongsTo(ApiProvider::class, 'supplier_id');
}

    public function group(){ return $this->belongsTo(ServiceGroup::class,'group_id'); }
}
