<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class MailTemplate extends Model
{
    protected $fillable = ['key', 'name', 'subject', 'body', 'audience', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
