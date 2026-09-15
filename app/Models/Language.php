<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Language extends Model
{
    protected $fillable = ['name', 'native_name', 'code', 'locale', 'direction', 'flag', 'is_default', 'active', 'ordering'];
    protected function casts(): array { return ['is_default' => 'boolean', 'active' => 'boolean', 'ordering' => 'integer']; }
    public function translations() { return $this->hasMany(LanguageTranslation::class); }
}
