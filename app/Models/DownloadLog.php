<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class DownloadLog extends Model {public $timestamps=false;protected $fillable=['user_id','ip_hash','user_agent_hash','downloaded_at'];protected function casts():array{return ['downloaded_at'=>'datetime'];}public function download(){return $this->belongsTo(Download::class);}public function user(){return $this->belongsTo(User::class);}}
