<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class Reseller extends Model {protected $fillable=['name','phone','whatsapp','country','description','image_path','active','ordering'];protected function casts():array{return ['active'=>'boolean','ordering'=>'integer'];}public function whatsappUrl():string{$number=preg_replace('/\D+/','',(string)($this->whatsapp?:$this->phone));return 'https://wa.me/'.$number;}}
