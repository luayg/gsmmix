<?php

namespace App\Http\Controllers\Admin\Users;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserIndexController extends Controller
{
    public function __invoke()
    {
        return view('admin.users.index');
    }

    // Select2: Roles
    public function roles(Request $r)
    {
        $term = trim($r->input('q',''));
        if (Schema::hasTable('roles')) {
            $items = DB::table('roles')
                ->when($term, fn($q)=>$q->where('name','like',"%{$term}%"))
                ->orderBy('name')->limit(50)->get(['name']);

            return response()->json([
                'results' => $items->map(fn($row)=>['id'=>$row->name,'text'=>$row->name])->values()
            ]);
        }
        $base = collect(['Administrator','Basic','Manager','Support']);
        if ($term) $base = $base->filter(fn($x)=>stripos($x,$term)!==false);
        return response()->json([
            'results' => $base->values()->map(fn($x)=>['id'=>$x,'text'=>$x])->all()
        ]);
    }

    // Select2: Groups
    public function groups(Request $r)
    {
        $term = trim($r->input('q',''));
        $items = Group::query()
            ->when($term, fn($q)=>$q->where('name','like',"%{$term}%"))
            ->orderBy('name')->limit(50)->get(['id','name'])
            ->map(fn($g)=>['id'=>$g->id,'text'=>$g->name]);
        return response()->json(['results'=>$items]);
    }
}
