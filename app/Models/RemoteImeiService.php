<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RemoteImeiService extends Model
{
    protected $table = 'remote_imei_services';
    protected $fillable = [
        'api_provider_id','remote_id','name','group_name','price','time','info',
        'network','mobile','provider','pin','kbh','mep','prd','type',
        'locks','reference','udid','serial','secro',
        'additional_fields','additional_data','params',
    ];
}
