<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class LanguageTranslation extends Model
{
    protected $fillable = ['language_id', 'translation_key', 'value'];
    public function language() { return $this->belongsTo(Language::class); }
}
