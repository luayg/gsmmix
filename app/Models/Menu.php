<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class Menu extends Model
{
    protected $fillable=['name','location','active'];
    protected function casts(): array { return ['active'=>'boolean']; }
    public function items(){ return $this->hasMany(MenuItem::class)->orderBy('ordering'); }
}
