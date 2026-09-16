<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class PageTranslation extends Model
{
    protected $fillable=['language_id','title','content','seo_title','seo_description'];
    public function page(){ return $this->belongsTo(Page::class); }
    public function language(){ return $this->belongsTo(Language::class); }
}
