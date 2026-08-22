@props(['navRail'])
@php
    use App\View\DataModels\NavRail;
    $NavRail = NavRail::from($navRail);
@endphp
<aside aria-label="{{$NavRail->label}}" class="fixed bottom-0 left-0 top-16 z-10 hidden w-56 border-r border-base-300 bg-base-200 lg:block">
    <ul class="menu w-full gap-1 p-2 mt-2">
        @foreach($NavRail->items as $NavItem)
            <li>
                <x-nav-link :navLink="$NavItem->navLink()"/>
            </li>
        @endforeach
    </ul>
</aside>
