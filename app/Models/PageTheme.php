<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class PageTheme extends Model
{
    protected $fillable=['name','settings','active'];
    protected function casts(): array{return ['settings'=>'array','active'=>'boolean'];}
}
