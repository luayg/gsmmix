<?php

namespace App\Http\Controllers\Admin\Services;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

abstract class BaseServiceController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;
    protected string $viewPrefix;   // server | file | imei
    protected string $routePrefix;  // admin.services.server | file | imei
    protected string $table;        // server_services | file_services | imei_services

    protected function rowsQuery()
    {
        /** @var Model $m */
        $m = app($this->model);
        return $m->newQuery()->with('group')->orderBy('id','asc');
    }

    public function index(Request $r)
    {
        $q = $this->rowsQuery();

        if ($r->filled('q')) {
            $term = $r->q;
            $q->where(function($qq) use ($term){
                $qq->where('alias','like',"%$term%")
                   ->orWhere('name','like',"%$term%");
            });
        }

        if ($r->filled('api_id')) {
            $q->where('source', (int)$r->api_id);
        }

        $rows = $q->paginate(20)->withQueryString();

        // اجلب Connections من نفس الجدول (Distinct source)
        $apis = app($this->model)->newQuery()
                 ->select('source')
                 ->whereNotNull('source')
                 ->groupBy('source')
                 ->pluck('source');

        return view("admin.services.{$this->viewPrefix}.index", [
            'rows' => $rows,
            'apis' => $apis,
            'routePrefix' => $this->routePrefix,
            'viewPrefix'  => $this->viewPrefix,
        ]);
    }

    public function create()
    {
        $row = app($this->model)->newInstance();
        return view("admin.services.{$this->viewPrefix}.form", $this->viewData($row));
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);
        $row = app($this->model)->create($data);
        return redirect()->route("{$this->routePrefix}.edit", $row)->with('ok','Created');
    }

    public function edit($id)
    {
        $row = app($this->model)->findOrFail($id);
        return view("admin.services.{$this->viewPrefix}.form", $this->viewData($row));
    }

    public function update(Request $r, $id)
    {
        $row = app($this->model)->findOrFail($id);
        $data = $this->validated($r);
        $row->update($data);
        return back()->with('ok','Saved');
    }

    public function destroy($id)
    {
        $row = app($this->model)->findOrFail($id);
        $row->delete();
        return back()->with('ok','Deleted');
    }

    public function show($id)
    {
        $row = app($this->model)->findOrFail($id);
        return view("admin.services.{$this->viewPrefix}.show", [
            'row'=>$row,
            'routePrefix'=>$this->routePrefix,
        ]);
    }

    public function copy($id)
    {
        $row = app($this->model)->findOrFail($id);
        $clone = $row->replicate();
        $clone->alias = Str::slug(($clone->alias ?: 'service').'-copy-'.time());
        $clone->save();

        return redirect()->route("{$this->routePrefix}.edit", $clone)->with('ok','Copied');
    }

    protected function viewData($row): array
    {
        $groups = \App\Models\ServiceGroup::orderBy('ordering')->orderBy('id')->get();
        $apis   = app($this->model)->newQuery()
                    ->select('source')->whereNotNull('source')->groupBy('source')->pluck('source');

        // استرجاع JSONs للتعديل (مطابق للأمثلة)
        $row->name_json = json_decode($row->name ?? '{}', true) ?: [];
        $row->time_json = json_decode($row->time ?? '{}', true) ?: [];
        $row->info_json = json_decode($row->info ?? '{}', true) ?: [];

        $main = json_decode($row->main_field ?? '{}', true) ?: [];
        $row->main_type    = Arr::get($main,'type','imei');
        $row->main_label   = Arr::get($main,'label.en','IMEI');
        $row->main_allowed = Arr::get($main,'rules.allowed','numbers');
        $row->min_qty      = Arr::get($main,'rules.minimum',15);
        $row->max_qty      = Arr::get($main,'rules.maximum',15);

        $row->meta = json_decode($row->params ?? '{}', true) ?: [];

        return [
            'row'=>$row,
            'groups'=>$groups,
            'apis'=>$apis,
            'routePrefix'=>$this->routePrefix,
            'viewPrefix'=>$this->viewPrefix,
        ];
    }

    protected function validated(Request $r): array
    {
        $v = $r->validate([
            'alias'           => 'nullable|string|max:255',
            'group_id'        => 'nullable|integer|exists:service_groups,id',
            'type'            => 'required|string|max:255',
            'allowed'         => 'nullable|string|max:50',
            'main_type'       => 'required|string|max:50',
            'main_label'      => 'nullable|string|max:255',
            'min_qty'         => 'nullable|integer',
            'max_qty'         => 'nullable|integer',
            'price'           => 'nullable|numeric',
            'converted_price' => 'nullable|numeric', // للعرض فقط
            'cost'            => 'nullable|numeric',
            'profit'          => 'nullable|numeric',
            'profit_type'     => 'nullable|integer',
            'source'          => 'nullable|integer',
            'remote_id'       => 'nullable|integer',
            'info_en'         => 'nullable|string',
            'name_en'         => 'required|string',
            'time_en'         => 'nullable|string',
            'meta_keywords'   => 'nullable|string',
            'meta_description'=> 'nullable|string',
            'after_head_open'   => 'nullable|string',
            'before_head_close' => 'nullable|string',
            'after_body_open'   => 'nullable|string',
            'before_body_close' => 'nullable|string',
            // Toggles
            'active'             => 'sometimes|boolean',
            'allow_bulk'         => 'sometimes|boolean',
            'allow_duplicates'   => 'sometimes|boolean',
            'reply_with_latest'  => 'sometimes|boolean',
            'allow_submit_verify'=> 'sometimes|boolean', // maps -> allow_report
            'allow_cancel'       => 'sometimes|boolean',
            'reply_expiration'   => 'sometimes|boolean',
        ]);

        $name = ['en'=>$v['name_en'],'fallback'=>$v['name_en']];
        $time = ['en'=>$v['time_en'] ?? '','fallback'=>$v['time_en'] ?? ''];
        $info = ['en'=>$v['info_en'] ?? '','fallback'=>$v['info_en'] ?? ''];

        $main = [
          'type'  => $v['main_type'],
          'rules' => ['allowed'=>$v['allowed'] ?? null,'minimum'=>$v['min_qty'] ?? null,'maximum'=>$v['max_qty'] ?? null],
          'label' => ['en'=>$v['main_label'] ?? '','fallback'=>$v['main_label'] ?? ''],
        ];

        $params = [
          'meta_keywords'           => $v['meta_keywords'] ?? '',
          'meta_description'        => $v['meta_description'] ?? '',
          'after_head_tag_opening'  => $v['after_head_open'] ?? '',
          'before_head_tag_closing' => $v['before_head_close'] ?? '',
          'after_body_tag_opening'  => $v['after_body_open'] ?? '',
          'before_body_tag_closing' => $v['before_body_close'] ?? '',
        ];

        return [
          'alias'          => $v['alias'] ?? null,
          'group_id'       => $v['group_id'] ?? null,
          'type'           => $v['type'],
          'name'           => json_encode($name, JSON_UNESCAPED_UNICODE),
          'time'           => json_encode($time, JSON_UNESCAPED_UNICODE),
          'info'           => json_encode($info, JSON_UNESCAPED_UNICODE),
          'main_field'     => json_encode($main, JSON_UNESCAPED_UNICODE),
          'params'         => json_encode($params, JSON_UNESCAPED_UNICODE),
          'cost'           => $v['cost'] ?? 0,
          'profit'         => $v['profit'] ?? 0,
          'profit_type'    => $v['profit_type'] ?? 1,
          'source'         => $v['source'] ?? null,
          'remote_id'      => $v['remote_id'] ?? null,
          'active'         => (int)$r->boolean('active'),
          'allow_bulk'     => (int)$r->boolean('allow_bulk'),
          'allow_duplicates' => (int)$r->boolean('allow_duplicates'),
          'reply_with_latest'=> (int)$r->boolean('reply_with_latest'),
          'allow_report'     => (int)$r->boolean('allow_submit_verify'),
          'allow_cancel'     => (int)$r->boolean('allow_cancel'),
          'reply_expiration' => (int)$r->boolean('reply_expiration'),
        ];
    }
}
