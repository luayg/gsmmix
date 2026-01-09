<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    public function index()
    {
        return view('admin.groups.index');
    }

    /** DataTables server-side */
    public function data(Request $r)
    {
        try {
            $base = Group::query()
                ->select('id','name','created_at');

            $recordsTotal = (clone $base)->count();

            // بحث
            $search = trim((string) data_get($r->input('search'), 'value', ''));
            if ($search !== '') {
                $base->where('name', 'like', "%{$search}%");
            }

            // ترتيب
            $map = [ 0=>'id', 1=>'name', 2=>null, 3=>'created_at' ];
            $col = (int) $r->input('order.0.column', 0);
            $dir = strtolower((string)$r->input('order.0.dir','asc')) === 'desc' ? 'desc' : 'asc';
            $orderCol = $map[$col] ?? 'id';
            if ($orderCol) $base->orderBy($orderCol, $dir); else $base->orderBy('id','asc');

            $recordsFiltered = (clone $base)->count();

            // ترقيم
            $start  = max(0,(int)$r->input('start',0));
            $length = (int)$r->input('length',10);
            if ($length < 1 || $length > 500) $length = 10;

            $rows = $base->skip($start)->take($length)->get();

            // جهّز عدد المستخدمين لكل جروب (اختياري/سريع)
            $counts = User::query()
                ->select('group_id', DB::raw('COUNT(*) as c'))
                ->whereIn('group_id', $rows->pluck('id')->filter())
                ->groupBy('group_id')
                ->pluck('c','group_id');

            $data = $rows->map(function($g) use ($counts){
                $viewBtn = '<button type="button" class="btn btn-primary btn-sm js-open-modal" data-url="/admin/groups/'.$g->id.'/modal/view"><i class="fas fa-eye"></i> View</button>';
                $editBtn = '<button type="button" class="btn btn-warning btn-sm js-open-modal" data-url="/admin/groups/'.$g->id.'/modal/edit"><i class="fas fa-edit"></i> Edit</button>';
                $delBtn  = '<button type="button" class="btn btn-danger btn-sm js-open-modal" data-url="/admin/groups/'.$g->id.'/modal/delete"><i class="fas fa-trash"></i> Delete</button>';

                return [
                    'id'      => $g->id,
                    'name'    => e($g->name),
                    'users'   => (int) ($counts[$g->id] ?? 0),
                    'created' => optional($g->created_at)->toDateString(),
                    'actions' => $viewBtn.' '.$editBtn.' '.$delBtn,
                ];
            });

            return response()->json([
                'data'            => $data,
                'recordsTotal'    => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
            ]);
        } catch (\Throwable $e) {
            Log::error('DT groups data failed', ['msg'=>$e->getMessage()]);
            return response()->json(['error'=>'Server error'], 500);
        }
    }

    public function show(Group $group)
    {
        return response()->json([
            'id'        => $group->id,
            'name'      => (string) $group->name,
            'created'   => optional($group->created_at)->format('Y-m-d H:i'),
            'users_cnt' => User::where('group_id', $group->id)->count(),
        ]);
    }

    /* ===== مودالات Ajax (Blade تُعرض داخل #ajaxModal) ===== */
    public function modalCreate()  { return view('admin.groups.modals.create'); }
    public function modalView(Group $group)   { return view('admin.groups.modals.view', compact('group')); }
    public function modalEdit(Group $group)   { return view('admin.groups.modals.edit', compact('group')); }
    public function modalDelete(Group $group) { return view('admin.groups.modals.delete', compact('group')); }

    /* ===== CRUD عبر Ajax ===== */
    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => ['required','string','max:255','unique:groups,name'],
        ]);

        $g = new Group();
        $g->name = $data['name'];
        $g->save();

        return response()->json(['ok'=>true,'msg'=>'Group created']);
    }

    public function update(Request $r, Group $group)
    {
        $data = $r->validate([
            'name' => ['required','string','max:255',"unique:groups,name,{$group->id}"],
        ]);

        $group->name = $data['name'];
        $group->save();

        return response()->json(['ok'=>true,'msg'=>'Group updated']);
    }

    public function destroy(Group $group)
    {
        $group->delete();
        return response()->json(['ok'=>true,'msg'=>'Group deleted']);
    }
}
