<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Setting extends Model
{
    protected $fillable = ['group_name', 'setting_key', 'value', 'value_type', 'is_encrypted'];

    protected $hidden = ['value'];

    protected function casts(): array
    {
        return ['is_encrypted' => 'boolean'];
    }
}
