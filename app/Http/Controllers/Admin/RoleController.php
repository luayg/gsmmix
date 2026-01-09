<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    /** صفحة القائمة */
    public function index()
    {
        return view('admin.roles.index');
    }

    /**
     * JSON لجدول DataTables
     * الأعمدة المتوقعة: [id, name, perms, created, actions]
     */
    public function data(Request $r)
    {
        $draw   = (int) $r->input('draw', 1);
        $start  = (int) $r->input('start', 0);
        $length = (int) $r->input('length', 10);
        $search = trim((string) data_get($r->input('search'), 'value', ''));

        $q = Role::query()
            ->where('guard_name', 'web')
            ->withCount(['permissions as perms'])   // alias يطابق عمود JS "perms"
            ->with('permissions:id,name');

        $recordsTotal = (clone $q)->count();

        if ($search !== '') {
            $q->where(function($w) use ($search){
                $w->where('name','like',"%{$search}%")
                  ->orWhereHas('permissions', fn($qq)=>$qq->where('name','like',"%{$search}%"));
            });
        }

        // ترتيب الأعمدة (0:id,1:name,2:perms,3:created_at)
        $map = [0=>'id', 1=>'name', 2=>'perms', 3=>'created_at'];
        $col = (int) $r->input('order.0.column', 1);
        $dir = strtolower((string) $r->input('order.0.dir','asc')) === 'desc' ? 'desc' : 'asc';
        $q->orderBy($map[$col] ?? 'name', $dir);

        $recordsFiltered = (clone $q)->count();

        $rows = $q->skip($start)->take($length)->get();

        $data = $rows->map(function (Role $role) {
            $actions = view('admin.roles._actions', ['role' => $role])->render();

            return [
                'id'      => $role->id,
                'name'    => e($role->name),
                'perms'   => (int) $role->perms,
                'created' => optional($role->created_at)->format('Y-m-d'),
                'actions' => $actions,
            ];
        });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    /** إنشاء دور جديد */
    public function store(Request $r)
    {
        $data = $r->validate([
            'name'       => 'required|string|max:100|unique:roles,name',
            'guard_name' => 'nullable|string|in:web',
        ]);
        $data['guard_name'] = $data['guard_name'] ?? 'web';

        $role = Role::create($data);

        return response()->json([
            'ok'  => true,
            'id'  => $role->id,
            'msg' => 'Role created successfully',
        ]);
    }

    /** تحديث دور */
    public function update(Request $r, Role $role)
    {
        $data = $r->validate([
            'name'       => 'required|string|max:100|unique:roles,name,'.$role->id,
            'guard_name' => 'nullable|string|in:web',
        ]);
        $data['guard_name'] = $data['guard_name'] ?? 'web';

        $role->update($data);

        return response()->json([
            'ok'  => true,
            'msg' => 'Role updated successfully',
        ]);
    }

    /** حذف دور */
    public function destroy(Role $role)
    {
        $role->delete();

        return response()->json([
            'ok'  => true,
            'msg' => 'Role deleted',
        ]);
    }

    /** صفحة إدارة الصلاحيات */
    public function editPerms(Role $role)
    {
        $all = Permission::where('guard_name', 'web')
            ->orderBy('name')
            ->get();

        return view('admin.roles.perms', [
            'role'        => $role->load('permissions'),
            'permissions' => $all,
        ]);
    }

    /**
     * حفظ الصلاحيات ثم العودة مباشرة لصفحة Roles
     * - إن كان الطلب JSON نرجع redirect داخل JSON (لازم لو مستقبلاً استعملت Ajax)
     */
    public function syncPerms(Request $request, Role $role)
    {
        $permInput = (array) $request->input('permissions', []);
        $role->syncPermissions($this->normalizePermissions($permInput));

        if ($request->wantsJson()) {
            return response()->json([
                'ok'       => true,
                'msg'      => 'Permissions updated',
                'redirect' => route('admin.roles.index'),
            ]);
        }

        // رجوع مباشر إلى صفحة الرولز + Session flash لعرض Toast عند الرجوع
        return redirect()
            ->route('admin.roles.index')
            ->with('ok', 'Permissions updated');
    }

    /** تطبيع قائمة الصلاحيات (IDs أو أسماء) إلى أسماء فقط */
    protected function normalizePermissions(array $items): array
    {
        if (empty($items)) return [];

        $first = reset($items);

        // IDs
        if (is_numeric($first)) {
            return Permission::whereIn('id', $items)
                ->where('guard_name', 'web')
                ->pluck('name')
                ->all();
        }

        // أسماء
        return Permission::whereIn('name', $items)
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();
    }
}
