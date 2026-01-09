<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UploadController extends Controller
{
    public function summernote(Request $request)
    {
        $request->validate([
            'image' => ['required','image','max:4096'], // 4MB
        ]);

        // public disk -> storage/app/public/editor/xxx.png
        $path = $request->file('image')->store('editor', 'public');

        return response()->json([
            'url' => asset('storage/'.$path),
        ]);
    }
}
