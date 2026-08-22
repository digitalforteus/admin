@php
    use App\Helpers\PublicAsset;
    use App\View\DataModels\Main;
    use App\View\DataModels\Nav;
    use App\View\DataModels\OrganizationSwitcher;
    $Main = Main::from($main);
@endphp
<!doctype html>
<html lang="{{str_replace('_', '-', app()->getLocale())}}"@if($Main->theme) data-theme="{{$Main->theme}}"@endif>
<head>
  <x-google-tag/>
  <x-google-tag-manager/>
  <meta charset="utf-8">
  <meta name="csrf-token" content="{{csrf_token()}}">
  <x-microsoft-site-verification/>
  <link rel="apple-touch-icon" sizes="180x180" href="{{PublicAsset::apple_touch_icon->url()}}">
  <link rel="icon" type="image/png" sizes="32x32" href="{{PublicAsset::favicon_32->url()}}">
  <link rel="icon" type="image/png" sizes="16x16" href="{{PublicAsset::favicon_16->url()}}">
  <link rel="manifest" href="{{PublicAsset::site_webmanifest->url()}}">
  @head
  @vite('resources/css/app.css')
</head>
<body class="h-screen overflow-y-scroll scrollbar-visible {{$Main->classnames}}">
<x-google-tag-manager-noscript/>
<x-topnav :topnav="$Main->topnav()"/>
@if($Main->nav === Nav::organization)
  @php($Switcher = OrganizationSwitcher::current())
  @if($Switcher !== null)
    <aside aria-label="Organization" class="fixed bottom-0 left-0 top-16 z-10 hidden w-56 bg-base-200 lg:block">
      <div class="py-2">
        <x-organization-switcher :organizationSwitcher="$Switcher->props()"/>
      </div>
      <ul class="menu w-full gap-1 p-2">
        @foreach($Main->nav->items() as $NavItem)
          <li>
            <a href="{{$NavItem->url()}}" @class(['menu-active' => $NavItem->active()])>
              <x-svg :svg="$NavItem->svg()"/>
              <span title="{{$NavItem->label}}">{{$NavItem->label}}</span>
            </a>
          </li>
        @endforeach
      </ul>
    </aside>
  @endif
@elseif($Main->nav)
  <x-nav-rail :navRail="$Main->nav->navRail()"/>
@endif
<div @class(['mt-16', 'lg:pl-56' => $Main->nav])>
  <div class="min-h-[calc(100vh-4rem)]">{{$slot}}</div>
</div>
<x-footer/>
@vite('resources/js/app.js')
</body>
</html>
