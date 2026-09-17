<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Passkey extends Model
{
    protected $fillable = ['user_id', 'name', 'credential_id', 'credential', 'last_used_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
