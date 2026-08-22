@php
    use App\View\DataModels\OrganizationNav;
    use App\View\DataModels\OrganizationSwitcher;
    $OrganizationSwitcher = OrganizationSwitcher::current();
@endphp
<aside aria-label="Organization" class="fixed bottom-0 left-0 top-16 z-10 hidden w-56 border-r border-base-300 bg-base-200 lg:block">
    @if($OrganizationSwitcher !== null)
        <div class="p-2">
            <x-organization-switcher :organizationSwitcher="$OrganizationSwitcher->props()"/>
        </div>
    @endif
    <ul class="menu w-full gap-1 p-2">
        @foreach(OrganizationNav::items() as $NavItem)
            <li>
                <a href="{{$NavItem->url()}}" @class(['menu-active' => $NavItem->active()])>
                    <x-svg :svg="$NavItem->svg()"/>
                    <span title="{{$NavItem->label}}">{{$NavItem->label}}</span>
                </a>
            </li>
        @endforeach
    </ul>
</aside>
