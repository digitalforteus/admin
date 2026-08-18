<?php

use App\Routes\Web;
use Illuminate\Support\Facades\Config;
use Laravel\Head\Facades\Head;

$appName = Config::string('app.name');

Head::title('MCP Server')
    ->description('Connect agents to the MCP development tools and API provided by '.$appName.'.');

?>
<x-main>
    <main class="mx-auto max-w-4xl px-6 py-10 lg:px-10 lg:py-16">
        <header class="max-w-3xl">
            <p class="text-sm font-semibold uppercase tracking-wider text-primary">Model Context Protocol</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight lg:text-4xl">Connect agents to {{$appName}}</h1>
            <p class="mt-4 text-lg leading-relaxed text-base-content/70">
                {{$appName}} supports local development tools and authenticated API operations through MCP-compatible clients.
            </p>
        </header>

        <div class="mt-10 grid gap-6 lg:grid-cols-2">
            <section class="border-2 border-base-300 bg-base-100 p-6" aria-labelledby="development-mcp-title">
                <h2 id="development-mcp-title" class="text-xl font-bold">Development MCP server</h2>
                <p class="mt-3 leading-relaxed text-base-content/70">
                    The repository includes a local MCP server for coding agents. Its tools scaffold API endpoint modules and
                    generate endpoint modules from OpenAPI 3 documents while following this application's conventions.
                </p>
                <p class="mt-5 text-sm font-semibold">Start it from the project root:</p>
                <code class="mt-2 block overflow-x-auto bg-base-200 p-4 font-mono text-sm">php artisan mcp:start project</code>
            </section>

            <section class="border-2 border-base-300 bg-base-100 p-6" aria-labelledby="api-mcp-title">
                <h2 id="api-mcp-title" class="text-xl font-bold">Application API through MCP</h2>
                <p class="mt-3 leading-relaxed text-base-content/70">
                    To let an agent use the hosted API, log in, create a bearer credential, and copy the MCP connection details
                    shown with that credential. Token abilities and expiration control what the agent can access.
                </p>
                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="{{Web::login->value}}" class="btn btn-primary">Log in for MCP access</a>
                    <a href="{{Web::llms->value}}" class="btn btn-outline">Agent Instructions</a>
                </div>
            </section>
        </div>
    </main>
</x-main>
