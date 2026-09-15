<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UploadController extends Controller
{
    public function summernote(Request $request)
    {
        $field = $request->hasFile('image') ? 'image' : 'file';
        $request->validate([
            $field => ['required','image','mimes:jpg,jpeg,png,webp,gif','max:4096'], // 4MB
        ]);

        // public disk -> storage/app/public/editor/xxx.png
        $path = $request->file($field)->store('editor', 'public');

        return response()->json([
            'url' => asset('storage/'.$path),
        ]);
    }
}
