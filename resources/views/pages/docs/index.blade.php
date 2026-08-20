<?php

use App\Routes\Web;
use Laravel\Head\Facades\Head;

Head::title('Documentation')
    ->description('Essential guides for developers.');
?>
<x-main>
    <div class="mx-auto max-w-5xl p-4 lg:p-6">
        <h1 class="text-3xl font-semibold">Documentation</h1>
        <p class="mt-2 text-base-content/70">Essential guides for developing with this platform.</p>

        <div class="mt-8 grid gap-6">
            <a href="{{Web::docsApi}}" class="card bg-base-100 shadow hover:shadow-lg transition-shadow">
                <div class="card-body">
                    <h2 class="card-title text-xl">REST API</h2>
                    <p>Endpoints, authentication, request/response formats, and usage patterns.</p>
                </div>
            </a>

            <a href="{{Web::docsApi}}" class="card bg-base-100 shadow hover:shadow-lg transition-shadow">
                <div class="card-body">
                    <h2 class="card-title text-xl">MCP (Model Context Protocol)</h2>
                    <p>Connect AI tools and IDEs to this API using the OpenAPI MCP Server.</p>
                </div>
            </a>
        </div>
    </div>
</x-main>
