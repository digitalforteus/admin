@props(['topnav'])
@php
    use App\Helpers\BrandLink;
    use App\Helpers\ProfilePicture;
    use App\Helpers\SvgName;
    use App\Routes\Web;
    use App\View\DataModels\ConnectionBreadcrumb;
    use App\View\DataModels\Svg;
    use App\View\DataModels\Topnav;
    use App\View\DataModels\UserMenu;
    $Topnav = Topnav::from($topnav);
    $picture = ProfilePicture::current();
@endphp
<div class="fixed top-0 z-20 shadow-md navbar bg-base-100">
    <div class="navbar-start">
        <div class="navbar-start">
            @if($Topnav->nav())
                <div class="dropdown lg:hidden">
                    <div tabindex="0" role="button" class="btn btn-ghost size-14" title="Open navigation">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16"/>
                        </svg>
                    </div>
                    <x-dropdown-menu>
                        @foreach($Topnav->items() as $NavItem)
                            <li>
                                <a href="{{$NavItem->url()}}" @class(['items-center gap-3 my-1 font-medium', 'menu-active' => $NavItem->active()])>
                                    <x-svg :svg="$NavItem->svg()"/>
                                    {{$NavItem->label}}
                                </a>
                            </li>
                        @endforeach
                    </x-dropdown-menu>
                </div>
            @endif
            <div class="flex items-center">
                <a href="{{Web::home->value}}"
                   class="btn btn-ghost no-animation hover:border-transparent hover:bg-transparent hover:shadow-none"
                   title="Go Home"
                >
                    <x-svg :svg="[Svg::name => SvgName::logo, Svg::classname => 'h-6 w-6']"/>
                </a>
                <span class="hidden items-baseline gap-1 lg:inline-flex" title="Brand Name">
                    <span><a href="{{Web::home->value}}">{{config('app.name')}}</a></span>
                    @if(config('brand.attribution'))
                        <span class="text-sm text-base-content/60">by</span>
                        <a href="{{BrandLink::header_lockup->url()}}"
                           class="text-sm font-semibold hover:underline"
                           data-digitalforte-link="header_lockup"><span class="text-digitalforte-primary">Digital</span><span class="text-digitalforte-secondary">Forte</span></a>
                    @endif
                </span>
            </div>
        </div>
    </div>
    <div class="gap-2 navbar-center">
        @php($ConnectionBreadcrumb = ConnectionBreadcrumb::current())
        @if($ConnectionBreadcrumb !== null)
            <x-connection-breadcrumb :connectionBreadcrumb="$ConnectionBreadcrumb->props()"/>
        @endif
    </div>
    <div class="navbar-end">
        @auth
            <x-user-menu :userMenu="[
                UserMenu::name => auth()->user()?->name ?? '',
                UserMenu::email => auth()->user()?->email ?? '',
                UserMenu::picture => $picture,
            ]"/>
        @else
            <div class="relative flex items-center">
                <a href="{{Web::contact->value}}" class="text-lg btn btn-ghost no-animation">
                    Contact
                </a>
                <a href="{{Web::login->value}}" class="text-lg btn btn-ghost no-animation">
                    Login
                </a>
            </div>
            @if(is_string(config('services.google.client_id')) && config('services.google.client_id') !== '')
                <div
                    data-google-one-tap
                    data-client-id="{{config('services.google.client_id')}}"
                    data-login-url="{{Web::googleOneTap->value}}"
                ></div>
            @endif
        @endauth
    </div>
</div>
@if(config('brand.attribution'))
    <script>
        document.querySelector('[data-digitalforte-link="header_lockup"]')?.addEventListener('click', (event) => {
            if (typeof gtag !== 'undefined') {
                gtag('event', 'digitalforte_referral_click', {
                    'link_placement': 'header_lockup',
                    'link_url': event.currentTarget.href
                });
            }
        });
    </script>
@endif
