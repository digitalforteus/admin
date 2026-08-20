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
                {{$appName}} an authenticated API operations through MCP-compatible clients.
            </p>
        </header>

        <div class="mt-10 grid gap-6 lg:grid-cols-2">
            <section class="border-2 border-base-300 bg-base-100 p-6" aria-labelledby="api-mcp-title">
                <h2 id="api-mcp-title" class="text-xl font-bold">Application API through MCP</h2>
                <p class="mt-3 leading-relaxed text-base-content/70">
                    To let an agent use the hosted API, log in, create a bearer credential, and copy the MCP connection details
                    shown with that credential. Token abilities and expiration control what the agent can access.
                </p>
                <div class="mt-5 flex flex-wrap gap-3">
                    @guest
                        <a href="{{Web::login->value}}" class="btn btn-primary">Log in for MCP access</a>
                    @endguest
                    <a href="{{Web::llms->value}}" class="btn btn-outline">Agent Instructions</a>
                </div>
            </section>
        </div>
    </main>
</x-main>
