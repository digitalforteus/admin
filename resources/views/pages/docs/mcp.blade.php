<?php

use App\Routes\Web;
use Laravel\Head\Facades\Head;

Head::title('MCP Documentation')
    ->description('Connect AI tools and IDEs using the OpenAPI MCP Server.')
    ->hiddenFromRobots();
?>
<x-main>
    <div class="mx-auto max-w-3xl space-y-8 p-4 lg:p-6">
        <div>
            <h1 class="text-3xl font-semibold">MCP: Connect AI to Your API</h1>
            <p class="mt-2 text-base-content/70">Use the <a href="{{Web::docsApi}}" class="underline">OpenAPI Schema</a> to connect to this website.</p>
        </div>

        <section>
            <h2 class="text-2xl font-semibold">What is MCP?</h2>
            <p class="mt-2 text-base-content/80">The Model Context Protocol (MCP) is a standard that allows AI systems, code editors, and other tools
                to discover and use your API through a standardized interface. Instead of manually documenting endpoints, MCP exposes your OpenAPI
                specification as tools the AI can call directly.</p>
        </section>

        <section>
            <h2 class="text-2xl font-semibold">Quick Start with Claude Desktop</h2>
            <div class="mt-4 space-y-4">
                <p class="text-base-content/80">1. Open your Claude Desktop configuration:</p>
                <div class="rounded bg-base-200 p-4 text-sm space-y-2">
                    <p><strong>macOS:</strong> <code>~/Library/Application Support/Claude/claude_desktop_config.json</code></p>
                    <p><strong>Windows:</strong> <code>%APPDATA%\Claude\claude_desktop_config.json</code></p>
                </div>

                <p class="text-base-content/80">2. Add this configuration under <code>mcpServers</code>:</p>
                <div class="rounded bg-base-200 p-4 overflow-x-auto">
                    <pre class="text-sm"><code>{
  "mcpServers": {
    "api": {
      "command": "npx",
      "args": ["-y", "@ivotoby/openapi-mcp-server"],
      "env": {
        "API_BASE_URL": "{{ url('') }}",
        "OPENAPI_SPEC_PATH": "{{ url(Web::openapi->value) }}",
        "API_HEADERS": "Authorization:Bearer YOUR_API_TOKEN"
      }
    }
  }
}</code></pre>
                </div>

                <p class="text-base-content/80">3. Replace <code>YOUR_API_TOKEN</code> with your API credentials token (see Settings → Credentials)
                </p>
                <p class="text-base-content/80">4. Restart Claude Desktop and your API endpoints will appear as tools</p>
            </div>
        </section>

        <section>
            <h2 class="text-2xl font-semibold">Configuration Options</h2>
            <div class="mt-4 space-y-4">
                <div class="rounded bg-base-100 p-4 border border-base-300">
                    <h3 class="font-semibold text-sm mb-2">API_BASE_URL</h3>
                    <p class="text-sm text-base-content/70">Base URL for API requests: <code>{{ url('') }}</code></p>
                </div>

                <div class="rounded bg-base-100 p-4 border border-base-300">
                    <h3 class="font-semibold text-sm mb-2">OPENAPI_SPEC_PATH</h3>
                    <p class="text-sm text-base-content/70">URL or local path to OpenAPI spec: <code>{{ url(Web::openapi->value) }}</code></p>
                </div>

                <div class="rounded bg-base-100 p-4 border border-base-300">
                    <h3 class="font-semibold text-sm mb-2">API_HEADERS</h3>
                    <p class="text-sm text-base-content/70">Comma-separated authentication headers:<br/><code>Authorization:Bearer
                            token,X-API-Key:key</code></p>
                </div>

                <div class="rounded bg-base-100 p-4 border border-base-300">
                    <h3 class="font-semibold text-sm mb-2">TOOLS_MODE</h3>
                    <p class="text-sm text-base-content/70"><code>all</code> (default) - expose all endpoints | <code>dynamic</code> - expose only
                        meta-tools for exploration</p>
                </div>
            </div>
        </section>

        <section>
            <h2 class="text-2xl font-semibold">Filtering Endpoints</h2>
            <p class="mt-2 text-base-content/80">Control which API endpoints are exposed as MCP tools:</p>
            <div class="mt-4 rounded bg-base-200 p-4 overflow-x-auto">
                <pre class="text-sm"><code>{
  "mcpServers": {
    "api": {
      "command": "npx",
      "args": [
        "-y",
        "@ivotoby/openapi-mcp-server",
        "--exclude-tag", "admin",
        "--exclude-tag", "internal"
      ],
      "env": { ... }
    }
  }
}</code></pre>
            </div>
            <p class="mt-3 text-base-content/80"><strong>Flags:</strong> <code>--tool</code>, <code>--tag</code>, <code>--exclude-tag</code>, <code>--resource</code>,
                <code>--operation</code></p>
        </section>

        <section>
            <h2 class="text-2xl font-semibold">Using with Other Tools</h2>
            <p class="mt-2 text-base-content/80">Beyond Claude Desktop, the OpenAPI MCP Server works with:</p>
            <ul class="mt-4 space-y-2 ml-4">
                <li>• <strong>Cursor</strong> – IDE integration with AI features</li>
                <li>• <strong>VS Code</strong> – With MCP client extension</li>
                <li>• <strong>HTTP Clients</strong> – Using HTTP transport instead of stdio</li>
                <li>• <strong>Custom Applications</strong> – Use as a library in Node.js</li>
            </ul>
        </section>

        <section>
            <h2 class="text-2xl font-semibold">HTTP Transport</h2>
            <p class="mt-2 text-base-content/80">For web applications and HTTP-only environments, run the server with HTTP transport:</p>
            <div class="mt-4 rounded bg-base-200 p-4 overflow-x-auto">
                <pre class="text-sm"><code>npx @ivotoby/openapi-mcp-server \
  --api-base-url {{ url('') }} \
  --openapi-spec {{ url(Web::openapi->value) }} \
  --headers "Authorization:Bearer YOUR_TOKEN" \
  --transport http \
  --port 3000</code></pre>
            </div>
            <p class="mt-3 text-base-content/80">Then send MCP requests to <code>http://localhost:3000/mcp</code></p>
        </section>

        <section>
            <h2 class="text-2xl font-semibold">Dynamic Tool Discovery</h2>
            <p class="mt-2 text-base-content/80">For large or evolving APIs, use dynamic mode to discover endpoints on-the-fly:</p>
            <div class="mt-4 rounded bg-base-200 p-4 overflow-x-auto">
                <pre class="text-sm"><code>{
  "mcpServers": {
    "api": {
      "command": "npx",
      "args": [
        "-y",
        "@ivotoby/openapi-mcp-server",
        "--tools", "dynamic"
      ],
      "env": { ... }
    }
  }
}</code></pre>
            </div>
            <p class="mt-3 text-base-content/80">This provides <code>list-api-endpoints</code>, <code>get-api-endpoint-schema</code>, and <code>invoke-api-endpoint</code>
                meta-tools instead of loading all endpoints upfront.</p>
        </section>

        <section>
            <h2 class="text-2xl font-semibold">Authentication</h2>
            <p class="mt-2 text-base-content/80">Generate API credentials in your account settings:</p>
            <ol class="mt-3 space-y-1 list-decimal list-inside text-base-content/80 ml-2">
                <li>Go to Settings → Credentials</li>
                <li>Click "Create Credential" and give it a name (e.g., "MCP Server")</li>
                <li>Copy the secret immediately</li>
                <li>Use it in the <code>API_HEADERS</code> configuration as the Bearer token</li>
            </ol>
        </section>
    </div>
</x-main>
