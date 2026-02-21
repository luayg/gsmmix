<?php

namespace App\Http\Controllers\Admin\Services;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ServiceGroup;

class ServiceGroupController extends Controller
{
    public function index(Request $request)
    {
        $q       = trim((string) $request->input('q', ''));
        $typeIn  = $request->input('type');
        $perPage = (int) $request->input('per_page', 20);

        $query = ServiceGroup::query()
            ->when($typeIn, function ($qr) use ($typeIn) {
                $normalized = $this->normalizeType($typeIn);
                $qr->where('type', $normalized);
            })
            ->when($q !== '', function ($qr) use ($q) {
                $qr->where(function ($sub) use ($q) {
                    if (ctype_digit($q)) $sub->orWhere('id', (int)$q);
                    $sub->orWhere('name', 'like', '%' . $q . '%');
                });
            })
            ->orderBy('name')
            ->orderBy('id');

        $rows = $query->paginate($perPage)->appends([
            'q'        => $q,
            'type'     => $typeIn,
            'per_page' => $perPage,
        ]);

        return view('admin.services.groups.index', compact('rows'));
    }

    public function create() { return view('admin.services.groups.create'); }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required','string'],
            'type'     => ['required','in:imei,server,file,imei_service,file_service,server_service'],
            'ordering' => ['nullable','integer','min:1'],
        ]);

        $data['type']     = $this->normalizeType($data['type']);
        $data['ordering'] = 1;

        ServiceGroup::create($data);

        return redirect()->route('admin.services.groups.index')->with('ok', 'Group added successfully.');
    }

    public function edit(ServiceGroup $group)
    {
        return view('admin.services.groups.edit', compact('group'));
    }

    public function update(Request $request, ServiceGroup $group)
    {
        $data = $request->validate([
            'name'     => ['required','string'],
            'type'     => ['required','in:imei,server,file,imei_service,file_service,server_service'],
            'ordering' => ['nullable','integer','min:1'],
        ]);

        $data['type']     = $this->normalizeType($data['type']);
        $data['ordering'] = $data['ordering'] ?? $group->ordering ?? 1;

        $group->update($data);

        return back()->with('ok', 'Saved.');
    }

    public function destroy(ServiceGroup $group)
    {
        $inUse = $this->countLinkedServices($group->id);
        if ($inUse > 0) {
            return back()->with('error', "Can't delete: group is linked to {$inUse} service(s).");
        }
        $group->delete();
        return back()->with('ok', 'Deleted.');
    }

    protected function normalizeType(?string $type): string
    {
        $type = strtolower((string)$type);
        return match ($type) {
            'imei', 'imei_service'       => 'imei_service',
            'file', 'file_service'       => 'file_service',
            'server', 'server_service'   => 'server_service',
            default                      => 'server_service',
        };
    }

    protected function countLinkedServices(int $groupId): int
    {
        $c1 = (int) DB::table('imei_services')->where('group_id', $groupId)->count();
        $c2 = (int) DB::table('server_services')->where('group_id', $groupId)->count();
        $c3 = (int) DB::table('file_services')->where('group_id', $groupId)->count();
        return $c1 + $c2 + $c3;
    }

    public function modalCreate()
{
    $group = null;
    return view('admin.services.groups._modal_form', compact('group'));
}

public function modalEdit(\App\Models\ServiceGroup $group)
{
    return view('admin.services.groups._modal_form', compact('group'));
}

public function modalDelete(\App\Models\ServiceGroup $group)
{
    return view('admin.services.groups._modal_delete', compact('group'));
}


    /**
     * ✅ AJAX: إرجاع الخيارات بحسب النوع (imei|server|file)
     * IMPORTANT:
     * - جدول service_groups يخزن النوع بصيغة: imei_service/server_service/file_service
     * - بينما المودال يرسل type بصيغة: imei/server/file
     * لذلك نعمل normalize قبل الفلترة.
     */
    public function options(Request $request)
    {
        $typeIn = strtolower((string) $request->get('type', ''));

        // فقط الأنواع المسموحة (لو غير ذلك رجّع كل الجروبات)
        $allowed = ['imei','imei_service','server','server_service','file','file_service'];

        $q = ServiceGroup::query();

        if ($typeIn !== '' && in_array($typeIn, $allowed, true)) {
            $normalized = strtolower($this->normalizeType($typeIn)); // => *_service
            $q->whereRaw('LOWER(type) = ?', [$normalized]);
        }

        $rows = $q->orderBy('name')->orderBy('id')->get(['id', 'name', 'type']);

        return response()->json(
            $rows->map(fn ($g) => [
                'id'   => $g->id,
                'name' => $g->name,
                'type' => $g->type,
            ])->values()
        );
    }
}
