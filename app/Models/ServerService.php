<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerService extends Model
{
    protected $table = 'server_services';
    public $timestamps = true;

    protected $fillable = [
        'icon','alias','name','time','info','cost','profit','profit_type','main_field','params',
        'active','allow_bulk','allow_duplicates','reply_with_latest','allow_report','allow_report_time',
        'allow_cancel','allow_cancel_time','use_remote_cost','use_remote_price','stop_on_api_change',
        'needs_approval','reply_expiration','expiration_text','type','group_id','source','remote_id',
        'supplier_id','local_source_id','device_based','reject_on_missing_reply','ordering'
    ];

    protected $casts = [
        'active'=>'boolean','allow_bulk'=>'boolean','allow_duplicates'=>'boolean',
        'reply_with_latest'=>'boolean','allow_report'=>'boolean','allow_cancel'=>'boolean',
        'use_remote_cost'=>'boolean','use_remote_price'=>'boolean','stop_on_api_change'=>'boolean',
        'needs_approval'=>'boolean','device_based'=>'boolean','reject_on_missing_reply'=>'boolean',
        'name'=>'array','time'=>'array','info'=>'array','main_field'=>'array','params'=>'array','expiration_text'=>'array'
    ];

    public function group(){ return $this->belongsTo(ServiceGroup::class,'group_id'); }
}
