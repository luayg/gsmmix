<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomField extends Model
{
    protected $table = 'custom_fields';

    protected $fillable = [
        'service_id',
        'service_type',
        'name',
        'input',
        'description',
        'field_type',
        'field_options',
        'validation',
        'minimum',
        'maximum',
        'required',
        'active',
        'ordering',
    ];

    protected $casts = [
        'required' => 'boolean',
        'active'   => 'boolean',
        'minimum'  => 'integer',
        'maximum'  => 'integer',
        'ordering' => 'integer',
    ];
}
