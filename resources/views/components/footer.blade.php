@php
    use App\Helpers\SvgName;
    use App\Routes\Web;
    use App\View\DataModels\Svg;
@endphp
<footer class="footer lg:footer-horizontal relative z-20 border-t border-base-300 bg-base-200 p-10 text-base-content">
    <aside>
        <a href="{{Web::home->value}}" class="flex items-center gap-2" title="Go Home">
            <x-svg :svg="[Svg::name => SvgName::logo, Svg::classname => 'h-8 w-8']"/>
            <span class="text-lg font-semibold" title="{{config('app.name')}}">{{config('app.name')}}</span>
        </a>
        <p class="flex items-center gap-2 text-sm text-base-content/70">
            Crafted by
            <a href="{{config('brand.digitalforte_url')}}?utm_source={{str(config('app.name'))->slug()}}&amp;utm_medium=referral&amp;utm_campaign=product_branding&amp;utm_content=footer_attribution"
               class="inline-flex items-center gap-1.5 font-semibold hover:underline"
               data-digitalforte-link="footer_attribution">
                <x-svg :svg="[Svg::name => SvgName::digitalforte_logo, Svg::classname => 'size-6 shrink-0']"/>
                <span><span class="text-digitalforte-primary">Digital</span><span class="text-digitalforte-secondary">Forte</span></span>
            </a>.
        </p>
    </aside>
    <nav>
        <h2 class="footer-title">Support</h2>
        <a href="{{Web::contact->value}}" class="link link-hover">Contact</a>
        @guest
            <a href="{{Web::login->value}}" class="link link-hover">Login</a>
        @endguest
    </nav>
    <nav>
        <h2 class="footer-title">Legal</h2>
        <a href="{{Web::privacyPolicy->value}}" class="link link-hover">Privacy Policy</a>
        <a href="{{Web::termsOfService->value}}" class="link link-hover">Terms of Service</a>
    </nav>
</footer>
<script>
    document.querySelector('[data-digitalforte-link="footer_attribution"]')?.addEventListener('click', (event) => {
        if (typeof gtag !== 'undefined') {
            gtag('event', 'digitalforte_referral_click', {
                'link_placement': 'footer_attribution',
                'link_url': event.currentTarget.href
            });
        }
    });
</script>
