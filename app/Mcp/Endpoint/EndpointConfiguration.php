<?php

namespace App\Mcp\Endpoint;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
readonly class EndpointConfiguration
{
    /**
     * @param  class-string  $route
     * @param  class-string  $schemaAttribute
     */
    public function __construct(
        public string $prefix,
        public string $route,
        public string $schemaAttribute,
        public string $routesFile,
        public ?string $authenticatedRoutesFile = null,
    ) {}

    public function routesFile(bool $authenticated): string
    {
        return $authenticated
            ? $this->authenticatedRoutesFile ?? $this->routesFile
            : $this->routesFile;
    }
}
