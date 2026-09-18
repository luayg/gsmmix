@php($children=$allItems->where('parent_id',$item->id))
<li class="menu-tree-item" draggable="true" data-item-id="{{ $item->id }}">
  <div class="menu-tree-row">
    <span class="menu-drag" title="Drag to reorder"><i class="fas fa-grip-vertical"></i></span>
    <button class="menu-branch-toggle" type="button" aria-label="Toggle children"><i class="fas {{ $children->isNotEmpty()?'fa-chevron-down':'fa-minus' }}"></i></button>
    <div class="flex-grow-1"><strong>{{ $item->label }}</strong><small>{{ $item->page?->slug ?? ($item->url ?: 'Branch / dropdown') }}</small></div>
    <button type="button" class="btn btn-sm btn-outline-info menu-edit-toggle"><i class="fas fa-pen"></i> Edit</button>
    <form method="POST" action="{{ route('admin.pages.menus.items.destroy',[$menu,$item]) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fas fa-eye-slash"></i> Remove</button></form>
  </div>
  <form class="menu-inline-edit" method="POST" action="{{ route('admin.pages.menus.items.update',[$menu,$item]) }}">@csrf @method('PUT')<input class="form-control" name="label" value="{{ $item->label }}" required><input class="form-control" name="url" value="{{ $item->url }}" placeholder="Optional /path or https://..."><label class="form-check"><input type="hidden" name="open_new_window" value="0"><input class="form-check-input" type="checkbox" name="open_new_window" value="1" @checked($item->open_new_window)> New window</label><button class="btn btn-sm btn-primary">Save</button></form>
  <ol class="menu-tree-children menu-drop-zone" data-parent-id="{{ $item->id }}">
    @foreach($children as $child)@include('admin.pages._menu-tree-item',['item'=>$child,'allItems'=>$allItems,'menu'=>$menu])@endforeach
  </ol>
</li>
