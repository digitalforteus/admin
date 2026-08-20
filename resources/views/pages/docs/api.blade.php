<?php

use App\Routes\ApiRoute;
use App\Routes\Web;
use App\View\DataModels\CopyLink;
use Laravel\Head\Facades\Head;

Head::title('API Documentation')
    ->description('REST API endpoints and usage.');
?>
<x-main>
    <div class="mx-auto max-w-3xl space-y-8 p-4 lg:p-6">
        <div>
            <h1 class="text-3xl font-semibold">API Documentation</h1>
            <p class="mt-2 text-base-content/70">
                <strong>OpenAPI Schema</strong>: <x-copy-link :copyLink="[CopyLink::value => url(Web::openapi->value)]">
                    <a href="{{Web::openapi->value}}" class="underline">{{url(Web::openapi->value)}}</a>
                </x-copy-link>
            </p>
        </div>

        <section>
            <h2 class="text-2xl font-semibold">Authentication</h2>
            <p class="mt-2 text-base-content/80">Requests are authenticated using Bearer tokens via the <code>Authorization</code> header:</p>
            <div class="mt-4 rounded bg-base-200 p-4">
                <code class="text-sm">Authorization: Bearer YOUR_TOKEN</code>
            </div>
            <p class="mt-4 text-base-content/80"><a href="{{Web::login->value}}" class="underline">Login</a> to Account Settings to generate and
                manage your API credentials</p>
        </section>

        <section>
            <h2 class="text-2xl font-semibold">Base URL</h2>
            <div class="mt-4 rounded bg-base-200 p-4">
                <code class="text-sm">{{ url(ApiRoute::prefix) }}</code>
            </div>
        </section>

        <section>
            <h2 class="text-2xl font-semibold">Response Format</h2>
            <p class="mt-2 text-base-content/80">All responses are JSON with this envelope:</p>
            <div class="mt-4 rounded bg-base-200 p-4 overflow-x-auto">
                <pre class="text-sm"><code>{
  "success": true,
  "message": "Operation completed",
  "data": { ... },
  "type": "ResponseClassName"
}</code></pre>
            </div>
        </section>

        <section>
            <h2 class="text-2xl font-semibold">Validation Errors</h2>
            <p class="mt-2 text-base-content/80">When validation fails (422), the response includes field-level errors:</p>
            <div class="mt-4 rounded bg-base-200 p-4 overflow-x-auto">
                <pre class="text-sm"><code>{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field_name": ["Error message"]
  }
}</code></pre>
            </div>
        </section>

        <section>
            <h2 class="text-2xl font-semibold">Rate Limiting</h2>
            <p class="mt-2 text-base-content/80">API requests are rate-limited per account. Exceeded limits return HTTP 429 with a
                <code>Retry-After</code> header.</p>
        </section>
    </div>
</x-main>
