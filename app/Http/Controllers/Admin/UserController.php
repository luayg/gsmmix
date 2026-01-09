<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        return view('admin.users.index');
    }

    /** DataTables (server-side) */
    public function data(Request $r)
    {
        try {
            $base = User::query()
                ->when(Schema::hasTable('roles') && Schema::hasTable('model_has_roles'), function ($q) {
                    $q->with(['roles:id,name']);
                })
                ->with(['group:id,name'])
                ->select('id','name','email','username','group_id','balance','status','created_at');

            $recordsTotal = (clone $base)->count();

            $search = trim((string) data_get($r->input('search'), 'value', ''));
            if ($search !== '') {
                $base->where(function($q) use ($search){
                    $q->where('name','like',"%{$search}%")
                      ->orWhere('email','like',"%{$search}%")
                      ->orWhere('username','like',"%{$search}%");
                });
            }

            // 0:id, 1:name, 2:email, 3:username, 4:roles(null), 5:group_id, 6:balance, 7:status
            $map = [
                0=>'id', 1=>'name', 2=>'email', 3=>'username',
                4=>null, 5=>'group_id', 6=>'balance', 7=>'status'
            ];
            $col = (int) $r->input('order.0.column', 0);
            $dir = strtolower((string)$r->input('order.0.dir','asc')) === 'desc' ? 'desc' : 'asc';
            $orderCol = $map[$col] ?? 'id';
            if ($orderCol) $base->orderBy($orderCol, $dir); else $base->orderBy('id', 'asc');

            $recordsFiltered = (clone $base)->count();

            $start  = max(0,(int)$r->input('start',0));
            $length = (int)$r->input('length',10);
            if ($length < 1 || $length > 500) $length = 10;

            $rows = $base->skip($start)->take($length)->get();

            $data = $rows->map(function($u){
                $viewBtn = '<button type="button" class="btn btn-primary btn-sm js-open-modal" data-url="/admin/users/'.$u->id.'/modal/view"><i class="fas fa-eye"></i> View</button>';

                $finBtn = '<button type="button" class="btn btn-info btn-sm js-open-modal" data-url="'.route('admin.users.finances.modal',$u->id).'"><i class="fas fa-wallet"></i> Finances</button>';

                $svcBtn  = '
                  <div class="btn-group">
                    <button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">Manage services</button>
                    <ul class="dropdown-menu">
                      <li><button class="dropdown-item js-open-modal" data-url="/admin/users/'.$u->id.'/modal/services">Open</button></li>
                    </ul>
                  </div>';

                $ordersBtn = '
                  <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">Orders</button>
                    <ul class="dropdown-menu">
                      <li><a class="dropdown-item" href="#">IMEI orders</a></li>
                      <li><a class="dropdown-item" href="#">Server orders</a></li>
                      <li><a class="dropdown-item" href="#">File orders</a></li>
                      <li><a class="dropdown-item" href="#">Product orders</a></li>
                    </ul>
                  </div>';

                $editBtn = '<button type="button" class="btn btn-warning btn-sm js-open-modal" data-url="/admin/users/'.$u->id.'/modal/edit"><i class="fas fa-edit"></i> Edit</button>';
                $delBtn  = '<button type="button" class="btn btn-danger btn-sm js-open-modal" data-url="/admin/users/'.$u->id.'/modal/delete"><i class="fas fa-trash"></i> Delete</button>';

                return [
                    'id'       => $u->id,
                    'name'     => e($u->name),
                    'email'    => e($u->email),
                    'username' => e($u->username ?? ''),
                    'roles'    => method_exists($u, 'roles') ? ($u->roles?->pluck('name')->implode(', ') ?? '') : '',
                    'group'    => optional($u->group)->name ?: '-',
                    'balance'  => number_format((float)$u->balance, 2), // ← الجديد
                    'status'   => $u->status,
                    'actions'  => $viewBtn.' '.$finBtn.' '.$svcBtn.' '.$ordersBtn.' '.$editBtn.' '.$delBtn,
                ];
            });

            return response()->json([
                'data'            => $data,
                'recordsTotal'    => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
            ]);
        } catch (\Throwable $e) {
            Log::error('DT users data failed', ['msg'=>$e->getMessage(), 'trace'=>$e->getTraceAsString()]);
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    public function show(User $user)
    {
        $user->loadMissing(['group:id,name']);
        if (Schema::hasTable('roles') && Schema::hasTable('model_has_roles')) {
            $user->loadMissing('roles:id,name');
        }

        return response()->json([
            'id'       => $user->id,
            'name'     => (string) $user->name,
            'email'    => (string) $user->email,
            'username' => (string) $user->username,
            'group'    => optional($user->group)->name ?: '-',
            'roles'    => $user->roles?->pluck('name')->values() ?? [],
            'status'   => $user->status,
            'created'  => optional($user->created_at)->format('Y-m-d H:i'),
        ]);
    }

    // =========================
    // Modals
    // =========================
    public function modalCreate()
    {
        $groups = Group::orderBy('name')->get(['id','name']);
        $roles  = Schema::hasTable('roles')
            ? DB::table('roles')->orderBy('name')->get(['name'])
            : collect(['Administrator','Basic','Manager','Support'])->map(fn($n)=>(object)['name'=>$n]);

        return view('admin.users.modals.create', compact('groups','roles'));
    }

    public function modalView(User $user)
    {
        $user->loadMissing('group','roles');
        return view('admin.users.modals.view', compact('user'));
    }

    public function modalEdit(User $user)
    {
        $user->loadMissing('group','roles');
        $groups = Group::orderBy('name')->get(['id','name']);
        $roles  = Schema::hasTable('roles')
            ? DB::table('roles')->orderBy('name')->get(['name'])
            : collect(['Administrator','Basic','Manager','Support'])->map(fn($n)=>(object)['name'=>$n]);

        return view('admin.users.modals.edit', compact('user','groups','roles'));
    }

    public function modalDelete(User $user)
    {
        return view('admin.users.modals.delete', compact('user'));
    }

    public function modalServices(User $user)
    {
        return response('<div class="modal-content"><div class="modal-header"><h5 class="modal-title">Services</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">Coming soon…</div></div>');
    }

    // =========================
    // Create
    // =========================
    public function store(Request $r)
    {
        $messages = [
            'name.required'      => 'الاسم مطلوب.',
            'email.required'     => 'البريد الإلكتروني مطلوب.',
            'email.email'        => 'صيغة البريد غير صحيحة.',
            'email.unique'       => 'هذا البريد الإلكتروني مُسجّل مسبقاً.',
            'username.unique'    => 'اسم المستخدم مُستخدم مسبقاً.',
            'status.required'    => 'الحالة مطلوبة.',
            'password.min'       => 'كلمة المرور يجب ألا تقل عن 8 أحرف.',
            'password.confirmed' => 'تأكيد كلمة المرور غير مطابق.',
        ];

        $data = $r->validate([
            'name'      => ['bail','required','string','max:255'],
            'email'     => ['bail','required','email','max:255','unique:users,email'],
            'username'  => ['nullable','string','max:255','unique:users,username'],
            'group_id'  => ['nullable','integer','exists:groups,id'],
            'status'    => ['required','in:active,inactive'],
            'roles'     => ['array'],
            'roles.*'   => ['string'],
            'password'  => ['nullable','string','min:8','confirmed'],
        ], $messages);

        $user = new User();
        $user->name     = $data['name'];
        $user->email    = $data['email'];
        $user->username = $data['username'] ?? null;
        $user->group_id = $data['group_id'] ?? null;
        $user->status   = $data['status'];
        $user->password = !empty($data['password'])
            ? Hash::make($data['password'])
            : Hash::make('password');
        $user->save();

        if (method_exists($user, 'syncRoles') && !empty($data['roles']) && Schema::hasTable('roles')) {
            $user->syncRoles($data['roles']);
        }

        return response()->json(['ok'=>true,'msg'=>'User created']);
    }

    // =========================
    // Update
    // =========================
    public function update(Request $r, User $user)
    {
        $messages = [
            'name.required'      => 'الاسم مطلوب.',
            'email.required'     => 'البريد الإلكتروني مطلوب.',
            'email.email'        => 'صيغة البريد غير صحيحة.',
            'email.unique'       => 'هذا البريد الإلكتروني مُسجّل مسبقاً.',
            'username.unique'    => 'اسم المستخدم مُستخدم مسبقاً.',
            'status.required'    => 'الحالة مطلوبة.',
            'password.min'       => 'كلمة المرور يجب ألا تقل عن 8 أحرف.',
            'password.confirmed' => 'تأكيد كلمة المرور غير مطابق.',
        ];

        $data = $r->validate([
            'name'      => ['required','string','max:255'],
            'email'     => ['required','email','max:255',"unique:users,email,{$user->id}"],
            'username'  => ['nullable','string','max:255',"unique:users,username,{$user->id}"],
            'group_id'  => ['nullable','integer','exists:groups,id'],
            'status'    => ['required','in:active,inactive'],
            'roles'     => ['array'],
            'roles.*'   => ['string'],
            'password'  => ['nullable','string','min:8','confirmed'],
        ], $messages);

        $user->fill([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'username' => $data['username'] ?? null,
            'group_id' => $data['group_id'] ?? null,
            'status'   => $data['status'],
        ])->save();

        if (!empty($data['password'])) {
            $user->forceFill(['password' => Hash::make($data['password'])])->save();
        }

        if (method_exists($user, 'syncRoles') && Schema::hasTable('roles')) {
            $user->syncRoles($data['roles'] ?? []);
        }

        return response()->json(['ok'=>true,'msg'=>'User updated']);
    }

    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(['ok'=>true,'msg'=>'User deleted']);
    }

    // =========================
    // Select2: Roles
    // =========================
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

    // =========================
    // Select2: Groups
    // =========================
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
