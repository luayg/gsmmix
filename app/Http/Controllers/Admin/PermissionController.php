<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index()
    {
        return view('admin.permissions.index');
    }

    /** DataTables (server-side) */
    public function data(Request $r)
    {
        $draw   = (int) $r->input('draw', 1);
        $start  = (int) $r->input('start', 0);
        $length = (int) $r->input('length', 10);
        $search = trim((string) data_get($r->input('search'), 'value', ''));

        $q = Permission::query()
            ->where('guard_name', 'web')
            ->withCount([
                'roles as roles_count',
                'users as users_count',
            ]);

        $recordsTotal = (clone $q)->count();

        if ($search !== '') {
            $q->where('name', 'like', "%{$search}%");
        }

        // 0:id,1:name,2:guard_name,3:roles_count,4:users_count,5:created_at
        $map = [0=>'id',1=>'name',2=>'guard_name',3=>'roles_count',4=>'users_count',5=>'created_at'];
        $col = (int) $r->input('order.0.column', 0);
        $dir = strtolower((string) $r->input('order.0.dir','asc')) === 'desc' ? 'desc' : 'asc';
        $q->orderBy($map[$col] ?? 'name', $dir);

        $recordsFiltered = (clone $q)->count();
        $rows = $q->skip($start)->take($length)->get();

        $data = $rows->map(function (Permission $p) {
            $actions = view('admin.permissions._actions', ['p' => $p])->render();

            return [
                'id'          => $p->id,
                'name'        => e($p->name),
                'guard'       => $p->guard_name,
                'roles_count' => (int) $p->roles_count,
                'users_count' => (int) $p->users_count,
                'created'     => optional($p->created_at)->format('Y-m-d'),
                'actions'     => $actions,
            ];
        });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    /** إنشاء صلاحية */
    public function store(Request $r)
    {
        $data = $r->validate([
            'name'       => ['required','string','max:190'],
            'guard_name' => ['nullable','string','max:50'],
        ]);
        $guard = $data['guard_name'] ?: 'web';

        $perm = Permission::firstOrCreate([
            'name'       => $data['name'],
            'guard_name' => $guard,
        ]);

        return response()->json(['ok' => true, 'id' => $perm->id, 'msg' => 'Permission created']);
    }

    /** تعديل صلاحية */
    public function update(Request $r, Permission $perm)
    {
        $data = $r->validate([
            'name'       => ['required','string','max:190'],
            'guard_name' => ['nullable','string','max:50'],
        ]);

        $perm->name       = $data['name'];
        $perm->guard_name = $data['guard_name'] ?: 'web';
        $perm->save();

        return response()->json(['ok' => true, 'msg' => 'Permission updated']);
    }

    /** حذف صلاحية */
    public function destroy(Permission $perm)
    {
        $perm->delete();
        return response()->json(['ok' => true, 'msg' => 'Permission deleted']);
    }
}
