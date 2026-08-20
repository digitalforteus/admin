<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ScaffoldEndpoint;
use App\Mcp\Tools\ScaffoldOpenApi;
use Illuminate\Support\Facades\Config;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Server\Tool;

class AppServer extends Server
{
    protected string $version = '0.1.0';
    protected string $instructions = <<<'MARKDOWN'
        This application's own tools. The zero-to-prod servers document the packages;
        this one writes code against the conventions this application keeps.

        - `scaffold-endpoint` — the artifacts of one API endpoint module
        - `scaffold-openapi` — endpoint modules for the operations in an OpenAPI 3.x document
        MARKDOWN;

    public function __construct(Transport $transport)
    {
        parent::__construct($transport);

        $this->name = Config::string('app.name');
    }

    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        ScaffoldEndpoint::class,
        ScaffoldOpenApi::class,
    ];
}
