<?php

namespace App\Mcp\Endpoint;

readonly class EndpointField
{
    private function __construct(
        public string $name,
        public string $type,
        public bool $nullable,
        public bool $required,
        public ?string $table,
        public string $column,
        public ?string $description,
        public ?string $itemsOf,
    ) {}

    /** @param  array<string, mixed>  $field */
    public static function from(array $field): self
    {
        $name = self::text($field, 'name') ?? '';

        return new self(
            name: $name,
            type: self::text($field, 'type') ?? 'string',
            nullable: ($field['nullable'] ?? false) === true,
            required: ($field['required'] ?? false) === true,
            table: self::text($field, 'table'),
            column: self::text($field, 'column') ?? $name,
            description: self::text($field, 'description'),
            itemsOf: self::text($field, 'items_of'),
        );
    }

    public function declaredType(): string
    {
        return ($this->nullable ? '?' : '').$this->type;
    }

    public function placeholder(): string
    {
        return EndpointFieldType::fromName($this->type)->placeholder();
    }

    public function reachesValidationError(): bool
    {
        return $this->required && ! $this->nullable && $this->type === 'string';
    }

    /** @param  array<string, mixed>  $field */
    private static function text(array $field, string $key): ?string
    {
        $value = $field[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
