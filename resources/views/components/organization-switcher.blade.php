@props(['organizationSwitcher'])
@php
    use App\Helpers\SvgName;
    use App\View\DataModels\Avatar;
    use App\View\DataModels\OrganizationSwitcher;
    use App\View\DataModels\Svg;
    $OrganizationSwitcher = OrganizationSwitcher::from($organizationSwitcher);
@endphp
<div class="dropdown w-full">
    <div tabindex="0" role="button" data-organization-switcher
         class="flex w-full items-center gap-3 rounded-box border border-base-300 bg-base-100 px-3 py-2 text-left"
         title="{{$OrganizationSwitcher->name}}">
        <x-avatar :avatar="[Avatar::name => $OrganizationSwitcher->name, Avatar::picture => $OrganizationSwitcher->iconUrl(), Avatar::size => 'w-8']"/>
        <div class="min-w-0 grow">
            <p class="truncate font-semibold" title="{{$OrganizationSwitcher->name}}">{{$OrganizationSwitcher->name}}</p>
            <p class="truncate text-xs opacity-60" title="{{$OrganizationSwitcher->enterprise}}">{{$OrganizationSwitcher->enterprise}}</p>
        </div>
        <span class="flex flex-col leading-none opacity-60">
            <x-svg :svg="[Svg::name => SvgName::chevron_up, Svg::classname => 'h-3 w-3']"/>
            <x-svg :svg="[Svg::name => SvgName::chevron_down, Svg::classname => 'h-3 w-3']"/>
        </span>
    </div>
    <x-dropdown-menu>
        @foreach($OrganizationSwitcher->sections() as $Group)
            <li class="menu-title" title="{{$Group->label}}">{{$Group->label}}</li>
            @foreach($Group->items() as $NavItem)
                <li>
                    <a href="{{$NavItem->url()}}" @class(['items-center gap-3 my-1 font-medium', 'menu-active' => $Group->isActive($NavItem)])>
                        <x-svg :svg="$NavItem->svg()"/>
                        <span class="grow truncate" title="{{$NavItem->label}}">{{$NavItem->label}}</span>
                        @if($Group->isActive($NavItem))
                            <x-svg :svg="[Svg::name => SvgName::check_circle, Svg::classname => 'h-4 w-4']"/>
                        @endif
                    </a>
                </li>
            @endforeach
        @endforeach
    </x-dropdown-menu>
</div>
