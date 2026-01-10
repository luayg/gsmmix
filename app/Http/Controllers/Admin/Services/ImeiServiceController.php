<?php

namespace App\Http\Controllers\Admin\Services;

use App\Http\Controllers\Controller;
use App\Models\ImeiService;
use App\Models\ServiceGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\ServiceGroupPrice;
use Illuminate\Support\Facades\DB;

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
        'alias'       => 'nullable|string',
        'time'        => 'nullable|string',
        'info'        => 'nullable|string',

        // Pricing
        'cost'        => 'required|numeric|min:0',
        'profit'      => 'nullable|numeric|min:0',
        'profit_type' => 'nullable|integer|in:1,2',

        // switches
        'active'              => 'nullable|boolean',
        'allow_bulk'          => 'nullable|boolean',
        'allow_duplicates'    => 'nullable|boolean',
        'reply_with_latest'   => 'nullable|boolean',
        'allow_report'        => 'nullable|boolean',
        'allow_report_time'   => 'nullable|integer|min:0',
        'allow_cancel'        => 'nullable|boolean',
        'allow_cancel_time'   => 'nullable|integer|min:0',
        'use_remote_cost'     => 'nullable|boolean',
        'use_remote_price'    => 'nullable|boolean',
        'stop_on_api_change'  => 'nullable|boolean',
        'needs_approval'      => 'nullable|boolean',
        'reply_expiration'    => 'nullable|integer|min:0',
        'expiration_text'     => 'nullable|string',

        // Source + Group + Type
        'source'              => 'nullable|integer',
        'type'                => 'nullable|string',
        'group_id'            => 'nullable|integer',
        'device_based'        => 'nullable|boolean',
        'reject_on_missing_reply' => 'nullable|boolean',
        'ordering'            => 'nullable|integer|min:1',

        // ✅ Pricing Table JSON
        'pricing_table'       => 'nullable|string',
    ]);

    $sourceInt = $r->has('source') ? (int)$r->input('source') : null;

    // ✅ Auto group by API group name
    $groupName = trim((string)($data['group_name'] ?? 'Uncategorized'));
    if ($groupName === '') $groupName = 'Uncategorized';

    $group = ServiceGroup::firstOrCreate([
        'type' => 'imei',
        'name' => $groupName,
    ], [
        'active' => 1
    ]);

    // ✅ Find existing by supplier_id + remote_id
    $exists = ImeiService::query()
        ->where('supplier_id', $data['supplier_id'])
        ->where('remote_id', $data['remote_id'])
        ->first();

    $payload = [
        'supplier_id' => (int)$data['supplier_id'],
        'group_id'    => $group->id,
        'remote_id'   => (int)$data['remote_id'],
        'alias'       => $data['alias'] ?: Str::slug($data['name']),
        'name'        => $data['name'],
        'time'        => $data['time'] ?? null,
        'info'        => $data['info'] ?? null,
        'cost'        => (float)$data['cost'],
        'profit'      => (float)($data['profit'] ?? 0),
        'profit_type' => (int)($data['profit_type'] ?? 1),

        // switches
        'active'            => (int)($data['active'] ?? 1),
        'allow_bulk'        => (int)($data['allow_bulk'] ?? 0),
        'allow_duplicates'  => (int)($data['allow_duplicates'] ?? 0),
        'reply_with_latest' => (int)($data['reply_with_latest'] ?? 0),

        'allow_report'      => (int)($data['allow_report'] ?? 0),
        'allow_report_time' => (int)($data['allow_report_time'] ?? 0),

        'allow_cancel'      => (int)($data['allow_cancel'] ?? 0),
        'allow_cancel_time' => (int)($data['allow_cancel_time'] ?? 0),

        'use_remote_cost'   => (int)($data['use_remote_cost'] ?? 0),
        'use_remote_price'  => (int)($data['use_remote_price'] ?? 0),
        'stop_on_api_change'=> (int)($data['stop_on_api_change'] ?? 0),
        'needs_approval'    => (int)($data['needs_approval'] ?? 0),

        'reply_expiration'  => (int)($data['reply_expiration'] ?? 0),
        'expiration_text'   => $data['expiration_text'] ?? null,

        'device_based'      => (int)($data['device_based'] ?? 0),
        'reject_on_missing_reply' => (int)($data['reject_on_missing_reply'] ?? 0),

        'ordering'          => (int)($data['ordering'] ?? 1),
        'source'            => $sourceInt,
    ];

    if ($exists) {
        $exists->update($payload);
        $service = $exists;
        $updated = true;
    } else {
        $service = ImeiService::create($payload);
        $updated = false;
    }

    // ✅ Save pricing table
    if (!empty($data['pricing_table'])) {
        $rows = json_decode($data['pricing_table'], true);

        if (is_array($rows)) {

            foreach ($rows as $row) {
                $groupId = (int)($row['group_id'] ?? 0);
                if (!$groupId) continue;

                \App\Models\ServiceGroupPrice::updateOrCreate([
                    'service_type' => 'imei',
                    'service_id'   => $service->id,
                    'group_id'     => $groupId,
                ], [
                    'price'         => (float)($row['price'] ?? 0),
                    'discount'      => (float)($row['discount'] ?? 0),
                    'discount_type' => (int)($row['discount_type'] ?? 1),
                ]);
            }
        }
    }

    return response()->json([
        'ok' => true,
        'updated' => $updated,
        'id' => $service->id,
        'msg' => $updated ? '✅ Service updated successfully' : '✅ Service added successfully'
    ]);
}


    /**
     * ✅ Save pricing values in service_group_prices table
     */
    private function saveGroupPrices(int $serviceId, array $groupPrices)
    {
        foreach ($groupPrices as $groupId => $row) {
            ServiceGroupPrice::updateOrCreate([
                'service_id'   => $serviceId,
                'service_kind' => 'imei',
                'group_id'     => (int)$groupId,
            ], [
                'price'    => (float)($row['price'] ?? 0),
                'discount' => (float)($row['discount'] ?? 0),
            ]);
        }
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

            // ✅ Additional Tab Pricing
            'group_prices'        => 'nullable|array',
            'group_prices.*.price'    => 'nullable|numeric|min:0',
            'group_prices.*.discount' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($service, $data) {

            $service->update([
                'name'        => $data['name'],
                'time'        => $data['time'] ?? null,
                'info'        => $data['info'] ?? null,
                'cost'        => (float)$data['cost'],
                'profit'      => (float)($data['profit'] ?? 0),
                'profit_type' => (int)($data['profit_type'] ?? 1),
                'active'      => (int)($data['active'] ?? 1),
            ]);

            // ✅ Save group prices
            $this->saveGroupPrices($service->id, $data['group_prices'] ?? []);

            return response()->json([
                'ok' => true,
                'msg' => '✅ Updated successfully'
            ]);
        });
    }
}
