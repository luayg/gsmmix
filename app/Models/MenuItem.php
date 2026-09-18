<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class MenuItem extends Model
{
    protected $fillable=['page_id','parent_id','label','url','open_new_window','ordering'];
    protected function casts(): array { return ['open_new_window'=>'boolean','ordering'=>'integer']; }
    public function menu(){ return $this->belongsTo(Menu::class); }
    public function page(){ return $this->belongsTo(Page::class); }
    public function parent(){ return $this->belongsTo(self::class,'parent_id'); }
    public function children(){ return $this->hasMany(self::class,'parent_id')->orderBy('ordering')->orderBy('id'); }
}
