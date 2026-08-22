@props(['connectionBreadcrumb'])
@php
    use App\Helpers\SvgName;
    use App\View\DataModels\ConnectionBreadcrumb;
    use App\View\DataModels\Svg;
    $ConnectionBreadcrumb = ConnectionBreadcrumb::from($connectionBreadcrumb);
@endphp
<div class="flex items-center gap-1 text-sm" data-connection-breadcrumb>
    <a href="{{$ConnectionBreadcrumb->url()}}" class="btn btn-ghost btn-sm max-w-40"
       title="{{$ConnectionBreadcrumb->organization}}">
        <span class="truncate">{{$ConnectionBreadcrumb->organization}}</span>
    </a>
    @if($ConnectionBreadcrumb->active !== null)
        <span class="opacity-40">/</span>
        <div class="dropdown dropdown-end">
            <div tabindex="0" role="button" class="btn btn-ghost btn-sm max-w-40" data-connection-switcher
                 title="{{$ConnectionBreadcrumb->active}}">
                <span class="truncate">{{$ConnectionBreadcrumb->active}}</span>
                <x-svg :svg="[Svg::name => SvgName::chevron_down, Svg::classname => 'h-3 w-3 opacity-60']"/>
            </div>
            <x-dropdown-menu>
                <li class="menu-title">Switch connection</li>
                @foreach($ConnectionBreadcrumb->items() as $NavItem)
                    <li>
                        <a href="{{$NavItem->url()}}" @class(['items-center gap-3 my-1 font-medium', 'menu-active' => $ConnectionBreadcrumb->isActive($NavItem)])>
                            <x-svg :svg="$NavItem->svg()"/>
                            <span class="grow truncate" title="{{$NavItem->label}}">{{$NavItem->label}}</span>
                            @if($ConnectionBreadcrumb->isActive($NavItem))
                                <x-svg :svg="[Svg::name => SvgName::check_circle, Svg::classname => 'h-4 w-4']"/>
                            @endif
                        </a>
                    </li>
                @endforeach
                <li>
                    <a href="{{$ConnectionBreadcrumb->settingsUrl()}}" class="items-center gap-3 my-1 font-medium">
                        <x-svg :svg="[Svg::name => SvgName::gear, Svg::classname => 'h-4 w-4 opacity-70']"/>
                        Connection settings
                    </a>
                </li>
            </x-dropdown-menu>
        </div>
    @endif
</div>
