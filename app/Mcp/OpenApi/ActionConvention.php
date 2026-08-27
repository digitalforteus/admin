<?php

namespace App\Mcp\OpenApi;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
readonly class ActionConvention
{
    /**
     * @param  list<string>  $methods
     * @param  list<string>  $operationPrefixes
     */
    public function __construct(
        public array $methods,
        public array $operationPrefixes,
        public ?bool $terminalParameter = null,
    ) {}

    public function supports(string $method, string $path): bool
    {
        return in_array($method, $this->methods, true)
            && ($this->terminalParameter === null || $this->terminalParameter === str_ends_with($path, '}'));
    }

    public function matches(string $operationId): bool
    {
        $operation = strtolower($operationId);

        return array_any(
            $this->operationPrefixes,
            static fn (string $prefix): bool => str_starts_with($operation, $prefix),
        );
    }
}
