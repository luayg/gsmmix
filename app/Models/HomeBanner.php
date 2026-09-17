<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class HomeBanner extends Model
{
    protected $fillable = ['image_path','title','text','button_label','button_url','ordering','active'];
    protected function casts(): array { return ['active'=>'boolean','ordering'=>'integer']; }
}
