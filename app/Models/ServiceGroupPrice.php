<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceGroupPrice extends Model
{
  protected $fillable = [
    'service_id',
    'service_kind',
    'group_id',
    'price',
    'discount',
  ];
}
