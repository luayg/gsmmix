@foreach($items->where('parent_id',$parentId) as $menuItem)
@php($navPage=$menuItem->page)
@if(!$navPage || ($navPage->status==='published' && (!$navPage->authenticated_only || auth()->check())))
@php($children=$items->where('parent_id',$menuItem->id))
@php($menuRoute=$navPage ? ($routeFor[$navPage->slug]??null) : null)
@php($menuHref=$navPage ? ($menuRoute&&Route::has($menuRoute)?route($menuRoute):route('site.page',$navPage)) : ($menuItem->url?:'#'))
<li class="{{ $parentId===null?'nav-item':'dropdown-submenu' }} {{ $children->isNotEmpty()?'dropdown':'' }}">
  <a class="nav-link {{ $children->isNotEmpty()?'dropdown-toggle':'' }}" href="{{ $menuHref }}" @if($children->isNotEmpty()) data-bs-toggle="dropdown" aria-expanded="false" @endif @if($menuItem->open_new_window) target="_blank" rel="noopener" @endif>{{ $menuItem->label }}</a>
  @if($children->isNotEmpty())<ul class="dropdown-menu">@include('layouts._site-menu-tree',['items'=>$items,'parentId'=>$menuItem->id,'routeFor'=>$routeFor])</ul>@endif
</li>
@endif
@endforeach
