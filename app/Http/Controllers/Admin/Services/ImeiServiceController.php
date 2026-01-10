<?php

namespace App\Http\Controllers\Admin\Services;

use App\Http\Controllers\Controller;
use App\Models\ImeiService;
use App\Models\ServiceGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ImeiServiceController extends Controller
{
    public function index()
    {
        $rows = ImeiService::query()
            ->with('group')
            ->orderBy('id', 'desc')
            ->paginate(25);

        return view('admin.services.imei.index', compact('rows'));
    }

    public function modalCreate(Request $r)
    {
        // ✅ يتم استدعاؤه من زر Clone / Add
        // البيانات تأتي من الـ HTML attributes
        $data = [
            'supplier_id' => $r->input('provider_id'),
            'remote_id'   => $r->input('remote_id'),
            'name'        => $r->input('name'),
            'cost'        => $r->input('credit'),
            'time'        => $r->input('time'),
            'group_name'  => $r->input('group'),
            'type'        => 'imei',
        ];

        return view('admin.services.imei._modal_create', compact('data'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'supplier_id' => 'required|integer',
            'remote_id'   => 'required',
            'group_name'  => 'nullable|string',
            'name'        => 'required|string',
            'time'        => 'nullable|string',
            'info'        => 'nullable|string',

            // Pricing
            'cost'        => 'required|numeric|min:0',
            'profit'      => 'nullable|numeric|min:0',
            'profit_type' => 'nullable|integer|in:1,2',

            // flags
            'active'      => 'nullable|boolean',
        ]);

        // ✅ Fix: تعريف sourceInt حتى لا يسبب Undefined variable
        // هذا الحقل موجود في الجدول كـ source (nullable)
        // إذا لم يرسل من الفورم نعطيه null
        $sourceInt = $r->has('source') ? (int)$r->input('source') : null;

        // ✅ احصل على الجروب أو أنشئه تلقائياً (بدون تكرار)
        $groupName = trim((string)($data['group_name'] ?? 'Uncategorized'));
        if ($groupName === '') $groupName = 'Uncategorized';

        $group = ServiceGroup::firstOrCreate([
            'type' => 'imei',
            'name' => $groupName,
        ], [
            'active' => 1
        ]);

        // ✅ منع التكرار: إذا موجودة بنفس supplier_id + remote_id
        $exists = ImeiService::query()
            ->where('supplier_id', $data['supplier_id'])
            ->where('remote_id', $data['remote_id'])
            ->first();

        $payload = [
            'supplier_id' => (int)$data['supplier_id'],
            'group_id'    => $group->id,
            'remote_id'   => (int)$data['remote_id'],
            'alias'       => (string)$data['remote_id'],
            'name'        => $data['name'],
            'time'        => $data['time'] ?? null,
            'info'        => $data['info'] ?? null,
            'cost'        => (float)$data['cost'],
            'profit'      => (float)($data['profit'] ?? 0),
            'profit_type' => (int)($data['profit_type'] ?? 1),
            'active'      => (int)($data['active'] ?? 1),

            // ✅ FIX: هنا نستخدم sourceInt بشكل صحيح
            'source'      => $sourceInt,
        ];

        if ($exists) {
            $exists->update($payload);
            return response()->json([
                'ok' => true,
                'updated' => true,
                'id' => $exists->id,
                'msg' => '✅ Service updated successfully'
            ]);
        }

        $row = ImeiService::create($payload);

        return response()->json([
            'ok' => true,
            'updated' => false,
            'id' => $row->id,
            'msg' => '✅ Service added successfully'
        ]);
    }

    public function modalEdit(ImeiService $service)
    {
        return view('admin.services.imei._modal_edit', compact('service'));
    }

    public function update(Request $r, ImeiService $service)
    {
        $data = $r->validate([
            'name'        => 'required|string',
            'time'        => 'nullable|string',
            'info'        => 'nullable|string',
            'cost'        => 'required|numeric|min:0',
            'profit'      => 'nullable|numeric|min:0',
            'profit_type' => 'nullable|integer|in:1,2',
            'active'      => 'nullable|boolean',
        ]);

        $service->update([
            'name'        => $data['name'],
            'time'        => $data['time'] ?? null,
            'info'        => $data['info'] ?? null,
            'cost'        => (float)$data['cost'],
            'profit'      => (float)($data['profit'] ?? 0),
            'profit_type' => (int)($data['profit_type'] ?? 1),
            'active'      => (int)($data['active'] ?? 1),
        ]);

        return response()->json([
            'ok' => true,
            'msg' => '✅ Updated successfully'
        ]);
    }
}
