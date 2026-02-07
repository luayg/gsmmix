<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomField extends Model
{
    protected $table = 'custom_fields';

    protected $fillable = [
        'service_id',
        'service_type',
        'ordering',
        'active',
        'required',
        'maximum',
        'minimum',
        'validation',
        'description',
        'field_options',
        'field_type',
        'input',
        'name',
    ];

    protected $casts = [
        'service_id' => 'integer',
        'ordering' => 'integer',
        'active' => 'integer',
        'required' => 'integer',
        'maximum' => 'integer',
        'minimum' => 'integer',
    ];
}
