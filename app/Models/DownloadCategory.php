<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class DownloadCategory extends Model {protected $fillable=['name','description','active','ordering','required_permission'];protected function casts():array{return ['active'=>'boolean','ordering'=>'integer'];}public function downloads(){return $this->hasMany(Download::class);}}
