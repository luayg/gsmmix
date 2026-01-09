<?php

namespace App\Http\Controllers\Admin\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class UserDataController extends Controller
{
    public function __invoke(Request $r)
    {
        try {
            $base = User::query()
                ->when(Schema::hasTable('roles') && Schema::hasTable('model_has_roles'), fn($q)=>$q->with(['roles:id,name']))
                ->with(['group:id,name'])
                ->select('id','name','email','username','group_id','status','created_at');

            $recordsTotal = (clone $base)->count();

            // Search
            $search = trim((string) data_get($r->input('search'),'value',''));
            if ($search !== '') {
                $base->where(function($q) use ($search){
                    $q->where('name','like',"%{$search}%")
                      ->orWhere('email','like',"%{$search}%")
                      ->orWhere('username','like',"%{$search}%");
                });
            }

            // Order
            $map = [0=>'id',1=>'name',2=>'email',3=>'username',4=>null,5=>'group_id',6=>'status',7=>'created_at'];
            $col = (int) $r->input('order.0.column', 0);
            $dir = strtolower((string)$r->input('order.0.dir','asc')) === 'desc' ? 'desc' : 'asc';
            $orderCol = $map[$col] ?? 'id';
            if ($orderCol) $base->orderBy($orderCol, $dir); else $base->orderBy('id','asc');

            $recordsFiltered = (clone $base)->count();

            // Paginate
            $start  = max(0,(int)$r->input('start',0));
            $length = (int)$r->input('length',10);
            if ($length < 1 || $length > 500) $length = 10;

            $rows = $base->skip($start)->take($length)->get();

            $data = $rows->map(function($u){
                $viewBtn = '<button type="button" class="btn btn-primary btn-sm js-open-modal" data-url="'.route('admin.users.modal.view',$u).'"><i class="fas fa-eye"></i> View</button>';
                $finBtn  = '<button type="button" class="btn btn-info btn-sm js-open-modal" data-url="'.route('admin.users.modal.finances',$u).'"><i class="fas fa-wallet"></i> Finances</button>';

                $svcBtn  = '
                  <div class="btn-group">
                    <button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">Manage services</button>
                    <ul class="dropdown-menu">
                      <li><button class="dropdown-item js-open-modal" data-url="'.route('admin.users.modal.services',$u).'">Open</button></li>
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

                $editBtn = '<button type="button" class="btn btn-warning btn-sm js-open-modal" data-url="'.route('admin.users.modal.edit',$u).'"><i class="fas fa-edit"></i> Edit</button>';
                $delBtn  = '<button type="button" class="btn btn-danger btn-sm js-open-modal" data-url="'.route('admin.users.modal.delete',$u).'"><i class="fas fa-trash"></i> Delete</button>';

                return [
                    'id'       => $u->id,
                    'name'     => e($u->name),
                    'email'    => e($u->email),
                    'username' => e($u->username ?? ''),
                    'roles'    => method_exists($u, 'roles') ? ($u->roles?->pluck('name')->implode(', ') ?? '') : '',
                    'group'    => optional($u->group)->name ?: '-',
                    'status'   => $u->status,
                    'created'  => optional($u->created_at)->toDateString(),
                    'actions'  => $viewBtn.' '.$finBtn.' '.$svcBtn.' '.$ordersBtn.' '.$editBtn.' '.$delBtn,
                ];
            });

            return response()->json([
                'data'            => $data,
                'recordsTotal'    => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
            ]);
        } catch (\Throwable $e) {
            Log::error('DT users data failed', ['msg'=>$e->getMessage()]);
            return response()->json(['error'=>'Server error'], 500);
        }
    }
}
