<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeBanner;
use App\Rules\SafeRasterImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class HomeBannerController extends Controller
{
    public function index() { return view('admin.settings.banners', ['banners'=>HomeBanner::query()->orderBy('ordering')->orderBy('id')->get()]); }

    public function store(Request $request): RedirectResponse
    {
        $data=$this->validated($request,true);
        $data['image_path']=$request->file('image')->store('banners','public');
        $data['active']=$request->boolean('active');
        HomeBanner::create($data);
        return back()->with('ok','Banner added.');
    }

    public function update(Request $request, HomeBanner $banner): RedirectResponse
    {
        $data=$this->validated($request,false);
        if($request->hasFile('image')){
            $new=$request->file('image')->store('banners','public');
            Storage::disk('public')->delete($banner->image_path);
            $data['image_path']=$new;
        }
        $data['active']=$request->boolean('active');
        $banner->update($data);
        return back()->with('ok','Banner updated.');
    }

    public function destroy(HomeBanner $banner): RedirectResponse
    {
        Storage::disk('public')->delete($banner->image_path);
        $banner->delete();
        return back()->with('ok','Banner deleted.');
    }

    private function validated(Request $request,bool $required): array
    {
        return $request->validate([
            'image'=>[$required?'required':'nullable','file','max:6144',new SafeRasterImage],
            'title'=>['nullable','string','max:120'],'text'=>['nullable','string','max:500'],
            'button_label'=>['nullable','string','max:50'],'button_url'=>['nullable','url:http,https','max:1000'],
            'ordering'=>['required','integer','min:0','max:100000'],'active'=>['sometimes','boolean'],
        ]);
    }
}
