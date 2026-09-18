<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class Page extends Model
{
    protected $fillable=['slug','status','placement','parent_id','featured_image','og_image','style','authenticated_only','open_new_window','system','ordering','published_at'];
    protected function casts(): array { return ['style'=>'array','authenticated_only'=>'boolean','open_new_window'=>'boolean','system'=>'boolean','ordering'=>'integer','published_at'=>'datetime']; }
    public function translations(){ return $this->hasMany(PageTranslation::class); }
    public function parent(){ return $this->belongsTo(self::class,'parent_id'); }
    public function children(){ return $this->hasMany(self::class,'parent_id'); }
}
