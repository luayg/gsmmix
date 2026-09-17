<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class LogController extends Controller
{
    public function access(Request $request)
    {
        $hasIp=Schema::hasColumn('access_logs','ip_address');
        $rows=DB::table('access_logs')->leftJoin('users','users.id','=','access_logs.user_id')->select('access_logs.*','users.name as user_name','users.email as user_email')->when($request->filled('event'),fn($q)=>$q->where('access_logs.event',$request->input('event')))->when($request->filled('successful'),fn($q)=>$q->where('access_logs.successful',$request->boolean('successful')))->when($hasIp&&$request->filled('ip'),fn($q)=>$q->where('access_logs.ip_address','like','%'.$request->string('ip').'%'))->orderByDesc('access_logs.id')->paginate(50)->withQueryString();
        if(!$hasIp)$rows->through(function($row){$row->ip_address=null;return $row;});
        $blocked=Schema::hasTable('blocked_ips')?DB::table('blocked_ips')->latest()->paginate(20,['*'],'blocked_page'):collect();
        return view('admin.logs.access',compact('rows','blocked'));
    }

    public function block(Request $request)
    {
        abort_unless(Schema::hasTable('blocked_ips'),503,'Run database migrations first.');
        $data=$request->validate(['ip_address'=>['required','ip'],'reason'=>['nullable','string','max:255'],'expires_at'=>['nullable','date','after:now']]);
        if (in_array($data['ip_address'],[(string)$request->ip(),'127.0.0.1','::1'],true)) throw ValidationException::withMessages(['ip_address'=>'You cannot block your current or loopback IP address.']);
        DB::table('blocked_ips')->updateOrInsert(['ip_address'=>$data['ip_address']],['reason'=>$data['reason']??null,'expires_at'=>$data['expires_at']??null,'active'=>true,'blocked_by'=>$request->user()->id,'updated_at'=>now(),'created_at'=>now()]);
        return back()->with('ok','IP address blocked.');
    }

    public function unblock(Request $request,int $blockedIp)
    {
        abort_unless(Schema::hasTable('blocked_ips'),404);
        DB::table('blocked_ips')->where('id',$blockedIp)->delete();
        return back()->with('ok','IP address unblocked.');
    }

    public function activity(Request $request){$rows=DB::table('activity_logs')->when($request->filled('q'),fn($q)=>$q->where(fn($q)=>$q->where('action','like','%'.$request->string('q').'%')->orWhere('route','like','%'.$request->string('q').'%')))->orderByDesc('id')->paginate(50)->withQueryString();return view('admin.logs.activity',compact('rows'));}
    public function error(Request $request){$files=collect(File::glob(storage_path('logs/*.log')))->map(fn($p)=>['name'=>basename($p),'size'=>File::size($p),'modified'=>File::lastModified($p)])->sortByDesc('modified')->values();$selected=basename((string)$request->input('file',''));$content='';if($selected&&$files->contains('name',$selected)){$path=storage_path('logs/'.$selected);$size=File::size($path);$handle=fopen($path,'rb');if($size>524288)fseek($handle,-524288,SEEK_END);$content=stream_get_contents($handle)?:'';fclose($handle);$content=preg_replace('/(password|token|secret|api[_-]?key|authorization)(["\'\s:=]+)[^\s,}\]]+/i','$1$2[REDACTED]',$content)??'';}return view('admin.logs.error',compact('files','selected','content'));}
}
