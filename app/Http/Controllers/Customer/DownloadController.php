<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Download;
use App\Models\DownloadPurchase;
use App\Models\FinanceTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class DownloadController extends Controller
{
    public function purchase(Request $request, Download $download)
    {
        $this->available($download);
        abort_unless($download->visibility === 'paid', 422);
        $result = DB::transaction(function () use ($request, $download) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $lockedDownload = Download::query()->lockForUpdate()->findOrFail($download->id);
            abort_unless($lockedDownload->active && $lockedDownload->visibility === 'paid' && (!$lockedDownload->expires_at || $lockedDownload->expires_at->isFuture()), 404);
            $existing = DownloadPurchase::query()->where('download_id', $download->id)->where('user_id', $request->user()->id)->first();
            if ($existing) return ['purchase'=>$existing,'charged'=>false];
            $price = bcadd((string)$lockedDownload->price, '0', 4);
            $before = bcadd((string)$user->balance, '0', 4);
            if (bccomp($before, $price, 4) < 0) throw ValidationException::withMessages(['balance' => 'Your balance is insufficient. Add funds to purchase this download.']);
            $after = bcsub($before, $price, 4);
            $user->forceFill(['balance' => $after])->save();
            $purchase = DownloadPurchase::create(['download_id'=>$download->id,'user_id'=>$user->id,'price'=>$price,'balance_before'=>$before,'balance_after'=>$after]);
            FinanceTransaction::create(['user_id'=>$user->id,'kind'=>'credit_remove','direction'=>'expense','paid'=>false,'amount'=>$price,'reference'=>'download:'.$lockedDownload->id,'note'=>'Purchased download: '.$lockedDownload->name,'balance_before'=>$before,'balance_after'=>$after,'source_type'=>DownloadPurchase::class,'source_id'=>$purchase->id]);
            return ['purchase'=>$purchase,'charged'=>true];
        }, 3);
        $purchase=$result['purchase'];
        return response()->json(['ok'=>true,'charged'=>$result['charged'],'download_url'=>route('customer.downloads.download',$download),'balance'=>$request->user()->fresh()->balance,'balance_before'=>$purchase->balance_before,'amount'=>$purchase->price,'purchase_id'=>$purchase->id]);
    }

    public function download(Request $request, Download $download)
    {
        $this->available($download);
        $this->authorized($request, $download);
        DB::transaction(function () use ($download, $request) {
            Download::query()->whereKey($download->id)->increment('download_count');
            $download->logs()->create(['user_id'=>$request->user()->id,'ip_hash'=>hash('sha256',(string)$request->ip().config('app.key')),'user_agent_hash'=>hash('sha256',(string)$request->userAgent()),'downloaded_at'=>now()]);
        });
        if ($download->source_type === 'external') return redirect()->away($download->external_url);
        abort_unless($download->storage_path && Storage::disk('local')->exists($download->storage_path), 404);
        return Storage::disk('local')->download($download->storage_path, $download->original_name ?: basename($download->storage_path), ['Content-Type'=>$download->mime_type ?: 'application/octet-stream','X-Content-Type-Options'=>'nosniff']);
    }

    private function available(Download $download): void { abort_unless($download->active && $download->visibility !== 'hidden' && (!$download->expires_at || $download->expires_at->isFuture()), 404); }
    private function authorized(Request $request, Download $download): void
    {
        if ($download->visibility === 'paid') abort_unless(DownloadPurchase::query()->where('download_id',$download->id)->where('user_id',$request->user()->id)->exists(), 402);
        if ($download->visibility !== 'private') return;
        $userRestricted=DB::table('download_user')->where('download_id',$download->id)->exists();$groupRestricted=DB::table('download_group')->where('download_id',$download->id)->exists();
        if (!$userRestricted && !$groupRestricted) return;
        $allowed=DB::table('download_user')->where('download_id',$download->id)->where('user_id',$request->user()->id)->exists() || ($request->user()->group_id && DB::table('download_group')->where('download_id',$download->id)->where('group_id',$request->user()->group_id)->exists());
        abort_unless($allowed,403);
    }
}
