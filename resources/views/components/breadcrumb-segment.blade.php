@props(['breadcrumbSegment'])
@php
    use App\Helpers\SvgName;
    use App\View\DataModels\BreadcrumbSegment;
    use App\View\DataModels\Svg;
    $BreadcrumbSegment = BreadcrumbSegment::from($breadcrumbSegment);
@endphp
<div class="flex items-center" data-breadcrumb-segment>
    <a href="{{$BreadcrumbSegment->url}}" class="btn btn-ghost btn-sm max-w-40 gap-2 px-2"
       title="{{$BreadcrumbSegment->label}}">
        <x-avatar :avatar="$BreadcrumbSegment->avatar()"/>
        <span class="truncate">{{$BreadcrumbSegment->label}}</span>
    </a>
    <div class="dropdown dropdown-end">
        <div tabindex="0" role="button" class="btn btn-ghost btn-sm px-1" data-breadcrumb-switcher
             title="{{$BreadcrumbSegment->switchLabel}}">
            <x-svg :svg="[Svg::name => SvgName::chevron_down, Svg::classname => 'h-3 w-3 opacity-60']"/>
        </div>
        <x-dropdown-menu>
            <li class="menu-title flex-row items-center justify-between gap-2 pr-1">
                <span class="truncate" title="{{$BreadcrumbSegment->label}}">{{$BreadcrumbSegment->label}}</span>
                @if($BreadcrumbSegment->settingsUrl !== null)
                    <a href="{{$BreadcrumbSegment->settingsUrl}}" class="btn btn-ghost btn-xs px-1"
                       data-breadcrumb-settings title="{{$BreadcrumbSegment->settingsLabel}}">
                        <x-svg :svg="[Svg::name => SvgName::gear, Svg::classname => 'h-4 w-4 opacity-70']"/>
                    </a>
                @endif
            </li>
            <li class="mx-2 my-1 border-t border-base-content/15"></li>
            @foreach($BreadcrumbSegment->entries() as $BreadcrumbItem)
                <li>
                    <a href="{{$BreadcrumbItem->url}}" class="items-center gap-3 my-1 font-medium">
                        <x-avatar :avatar="$BreadcrumbItem->avatar()"/>
                        <span class="grow truncate" title="{{$BreadcrumbItem->label}}">{{$BreadcrumbItem->label}}</span>
                    </a>
                </li>
            @endforeach
            @if($BreadcrumbSegment->createUrl !== null)
                <li>
                    <a href="{{$BreadcrumbSegment->createUrl}}" class="items-center gap-3 my-1 font-medium" data-breadcrumb-create>
                        <x-svg :svg="[Svg::name => SvgName::plus, Svg::classname => 'h-4 w-4 opacity-70']"/>
                        <span class="grow truncate" title="{{$BreadcrumbSegment->createLabel}}">{{$BreadcrumbSegment->createLabel}}</span>
                    </a>
                </li>
            @endif
        </x-dropdown-menu>
    </div>
</div>
