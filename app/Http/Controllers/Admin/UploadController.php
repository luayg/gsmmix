<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Rules\SafeRasterImage;

class UploadController extends Controller
{
    public function summernote(Request $request)
    {
        $field = $request->hasFile('image') ? 'image' : 'file';
        $request->validate([
            $field => ['required','file','max:4096',new SafeRasterImage],
        ]);

        // public disk -> storage/app/public/editor/xxx.png
        $path = $request->file($field)->store('editor', 'public');

        return response()->json([
            'url' => asset('storage/'.$path),
        ]);
    }
}
