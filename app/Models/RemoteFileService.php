<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RemoteFileService extends Model
{
    protected $table = 'remote_file_services';
    protected $fillable = [
        'api_provider_id','remote_id','name','group_name','price','time','info',
        'allowed_extensions','additional_fields','additional_data','params',
    ];
}
