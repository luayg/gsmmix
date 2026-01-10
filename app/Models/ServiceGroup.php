<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceGroup extends Model
{
    public $timestamps = true;
    protected $fillable = ['name','type','ordering'];

    public function imeiServices(){ return $this->hasMany(ImeiService::class,'group_id'); }
    public function serverServices(){ return $this->hasMany(ServerService::class,'group_id'); }
    public function fileServices(){ return $this->hasMany(FileService::class,'group_id'); }

    public function imeiPrices()
{
    return $this->hasMany(\App\Models\ServiceGroupPrice::class, 'group_id')
                ->where('service_type', 'imei');
}



}
