@props(['userMenu' => []])
@php
    use App\View\DataModels\UserMenu;
    $UserMenu = UserMenu::from($userMenu);
@endphp
<div class="dropdown dropdown-end">
    <div tabindex="0" role="button"
         @class(['btn btn-ghost btn-circle avatar size-14', 'avatar-placeholder' => $UserMenu->picture() === null]) title="{{$UserMenu->name}}">
        @if($UserMenu->picture() !== null)
            <div class="w-9 rounded-full text-neutral-content">
                <img src="{{$UserMenu->picture()}}" alt="{{$UserMenu->name}}" referrerpolicy="no-referrer">
            </div>
        @else
            <div class="w-9 rounded-full bg-neutral text-neutral-content">
                <span class="text-sm" title="{{$UserMenu->initials()}}">{{$UserMenu->initials()}}</span>
            </div>
        @endif
    </div>
    <x-dropdown-menu>
        <li class="menu-title">
            <div class="flex items-center gap-3">
                <div @class(['avatar', 'avatar-placeholder' => $UserMenu->picture() === null])>
                    @if($UserMenu->picture() !== null)
                        <div class="w-9 rounded-full text-neutral-content">
                            <img src="{{$UserMenu->picture()}}" alt="{{$UserMenu->name}}" referrerpolicy="no-referrer">
                        </div>
                    @else
                        <div class="w-9 rounded-full bg-neutral text-neutral-content">
                            <span class="text-sm" title="{{$UserMenu->initials()}}">{{$UserMenu->initials()}}</span>
                        </div>
                    @endif
                </div>
                <div class="min-w-0">
                    <p class="truncate font-semibold text-base-content" title="{{$UserMenu->name}}">{{$UserMenu->name}}</p>
                    <p class="truncate text-xs font-normal opacity-60" title="{{$UserMenu->email}}">{{$UserMenu->email}}</p>
                </div>
            </div>
        </li>
        @foreach(UserMenu::items() as $NavItem)
            <li>
                <a href="{{$NavItem->url()}}" class="items-center gap-3 my-1 font-medium">
                    <x-svg :svg="$NavItem->svg()"/>
                    {{$NavItem->label}}
                </a>
            </li>
        @endforeach
    </x-dropdown-menu>
</div>
