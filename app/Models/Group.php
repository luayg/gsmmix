<?php
// [انسخ]
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = ['name'];
    // علاقات (لو المستخدمين يرتبطوا بالمجموعة):
    public function users(){ return $this->hasMany(User::class); }
}
