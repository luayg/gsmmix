<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class DownloadPurchase extends Model {protected $fillable=['download_id','user_id','price','balance_before','balance_after'];protected function casts():array{return ['price'=>'decimal:4','balance_before'=>'decimal:4','balance_after'=>'decimal:4'];}public function download(){return $this->belongsTo(Download::class);}public function user(){return $this->belongsTo(User::class);}}
