<?php

use App\Models\User;
use App\Modules\Settings\Organizations\OrganizationQuery;
use App\Routes\Web;
use App\View\DataModels\Avatar;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Laravel\Head\Facades\Head;

$appName = Config::string('app.name');
$appDescription = 'An opinionated Laravel application.';

Head::title($appName)->description($appDescription);

$homeUrl = url(Web::home->value);
$llmsUrl = url(Web::llms->value);
$siteLinks = [
    ['name' => 'OpenAPI Spec', 'url' => url(Web::openapi->value)],
    ['name' => 'Login', 'url' => url(Web::login->value)],
    ['name' => 'MCP Server', 'url' => url(Web::mcp->value)],
    ['name' => 'Agent Instructions', 'url' => $llmsUrl],
    ['name' => 'Contact '.$appName, 'url' => url(Web::contact->value)],
];

$structuredData = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Organization',
            '@id' => $homeUrl.'#organization',
            'name' => $appName,
            'url' => $homeUrl,
        ],
        [
            '@type' => 'WebSite',
            '@id' => $homeUrl.'#website',
            'name' => $appName,
            'url' => $homeUrl,
            'publisher' => ['@id' => $homeUrl.'#organization'],
        ],
        [
            '@type' => 'WebPage',
            '@id' => $homeUrl.'#webpage',
            'name' => $appName,
            'url' => $homeUrl,
            'description' => $appDescription,
            'isPartOf' => ['@id' => $homeUrl.'#website'],
            'about' => ['@id' => $homeUrl.'#application'],
            'mainEntity' => ['@id' => $homeUrl.'#application'],
        ],
        [
            '@type' => 'WebApplication',
            '@id' => $homeUrl.'#application',
            'name' => $appName,
            'url' => $homeUrl,
            'description' => $appDescription,
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Any',
            'browserRequirements' => 'Requires a modern web browser with JavaScript enabled.',
            'featureList' => [
                'Secure client accounts',
                'Bearer-token API access',
                'MCP-compatible agent access',
            ],
            'isPartOf' => ['@id' => $homeUrl.'#website'],
            'provider' => ['@id' => $homeUrl.'#organization'],
            'mainEntityOfPage' => ['@id' => $homeUrl.'#webpage'],
        ],
        [
            '@type' => 'SoftwareApplication',
            '@id' => $homeUrl.'#mcp-server',
            'name' => $appName.' MCP Server',
            'url' => url(Web::mcp->value),
            'description' => 'Local Model Context Protocol tools for developing '.$appName.'.',
            'applicationCategory' => 'DeveloperApplication',
            'operatingSystem' => 'Any',
            'isPartOf' => ['@id' => $homeUrl.'#application'],
            'provider' => ['@id' => $homeUrl.'#organization'],
        ],
        [
            '@type' => 'ItemList',
            '@id' => $homeUrl.'#site-links',
            'name' => $appName.' site links',
            'itemListElement' => array_map(
                static fn(array $link, int $index): array => [
                    '@type' => 'SiteNavigationElement',
                    'position' => $index + 1,
                    ...$link,
                ],
                $siteLinks,
                array_keys($siteLinks),
            ),
        ],
    ],
];

?>
<x-main>
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    <x-status-toast/>

    @auth
        @php
            $User = Auth::user();
            if ($User instanceof User) {
                $Organizations = OrganizationQuery::get($User);
                $organizationCards = array_map(static function (array $org): array {
                    return [
                        ...$org,
                        'url' => '/o/' . $org['slug'],
                    ];
                }, $Organizations);
            } else {
                $organizationCards = [];
            }
        @endphp
        @if ($organizationCards)
            <nav aria-labelledby="organizations-title" class="mx-auto max-w-6xl px-6 pb-16 lg:px-10 lg:pb-20">
                <h2 id="organizations-title" class="mb-4 text-xl font-bold">Your organizations</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($organizationCards as $card)
                        <a href="{{ $card['url'] }}" class="group border border-base-300 bg-base-100 p-6 hover:border-primary">
                            <div class="flex items-center gap-3">
                                <x-avatar :avatar="[Avatar::name => $card['name'], Avatar::picture => $card['icon'], Avatar::size => 'w-10']"/>
                                <span class="text-lg font-semibold text-primary group-hover:underline">{{ $card['name'] }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </nav>
        @endif
    @endauth
</x-main>
