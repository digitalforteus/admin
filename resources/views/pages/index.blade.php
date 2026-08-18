<?php

use App\Routes\Web;
use Illuminate\Support\Facades\Config;
use Laravel\Head\Facades\Head;

$appName = Config::string('app.name');
$appDescription = 'An opinionated Laravel application.';

Head::title($appName)->description($appDescription);

$homeUrl = url(Web::home->value);
$llmsUrl = url(Web::llms->value);
$siteLinks = [
    ['name' => 'OpenAPI Spec', 'url' => url(Web::openapi->value)],
    ['name' => 'MCP Access', 'url' => url(Web::login->value)],
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
                static fn (array $link, int $index): array => [
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

    <nav aria-label="Client and contact links" class="mx-auto grid max-w-6xl gap-4 px-6 pb-16 lg:grid-cols-2 lg:px-10 lg:pb-20">
        @guest
            <a href="{{Web::login->value}}" class="group border border-base-300 bg-base-100 p-6 hover:border-primary" data-home-login>
                <span class="text-lg font-semibold text-primary group-hover:underline">Login</span>
                <span class="mt-2 block text-sm leading-relaxed text-base-content/70">
                    Sign in securely to access your client account, software services, and API credentials.
                </span>
            </a>
        @endguest
        <a href="{{Web::contact->value}}" class="group border border-base-300 bg-base-100 p-6 hover:border-primary">
            <span class="text-lg font-semibold text-primary group-hover:underline">Contact</span>
            <span class="mt-2 block text-sm leading-relaxed text-base-content/70">
                Talk with us about automation, consulting, custom development, or account support.
            </span>
        </a>
    </nav>

    <nav aria-labelledby="site-links-title" class="mx-auto max-w-6xl px-6 pb-16 lg:px-10 lg:pb-20">
        <h2 id="site-links-title" class="mb-4 text-xl font-bold">Explore</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <a href="{{Web::openapi->value}}" class="group border border-base-300 bg-base-100 p-6 hover:border-primary">
                <span class="text-lg font-semibold text-primary group-hover:underline">OpenAPI Spec</span>
                <span class="mt-2 block text-sm leading-relaxed text-base-content/70">
                    Download the machine-readable contract for {{$appName}}'s public API.
                </span>
            </a>
            <a href="{{Web::login->value}}" class="group border border-base-300 bg-base-100 p-6 hover:border-primary">
                <span class="text-lg font-semibold text-primary group-hover:underline">MCP Access</span>
                <span class="mt-2 block text-sm leading-relaxed text-base-content/70">
                    Log in to create credentials and connect an agent through MCP.
                </span>
            </a>
            <a href="{{Web::mcp->value}}" class="group border border-base-300 bg-base-100 p-6 hover:border-primary">
                <span class="text-lg font-semibold text-primary group-hover:underline">MCP Server</span>
                <span class="mt-2 block text-sm leading-relaxed text-base-content/70">
                    Connect coding agents to the development tools included with {{$appName}}.
                </span>
            </a>
            <a href="{{Web::llms->value}}" class="group border border-base-300 bg-base-100 p-6 hover:border-primary">
                <span class="text-lg font-semibold text-primary group-hover:underline">Agent Instructions</span>
                <span class="mt-2 block text-sm leading-relaxed text-base-content/70">
                    Give agents concise instructions for understanding and using {{$appName}}.
                </span>
            </a>
            <a href="{{Web::contact->value}}" class="group border border-base-300 bg-base-100 p-6 hover:border-primary">
                <span class="text-lg font-semibold text-primary group-hover:underline">Contact {{$appName}}</span>
                <span class="mt-2 block text-sm leading-relaxed text-base-content/70">
                    Ask about services, integrations, account access, or support.
                </span>
            </a>
        </div>
    </nav>

</x-main>
