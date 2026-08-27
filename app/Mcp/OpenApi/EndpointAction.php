<?php

namespace App\Mcp\OpenApi;

use App\Helpers\HasEnumAttributes;
use InvalidArgumentException;

enum EndpointAction: string
{
    use HasEnumAttributes;

    #[ActionConvention(['get'], ['list', 'getall'], terminalParameter: false)]
    case index = 'Index';

    #[ActionConvention(['get'], ['get', 'show'], terminalParameter: true)]
    case show = 'Show';

    #[ActionConvention(['post'], ['add', 'create', 'store'])]
    case store = 'Store';

    #[ActionConvention(['put', 'patch'], ['update'])]
    case update = 'Update';

    #[ActionConvention(['delete'], ['delete', 'remove'])]
    case delete = 'Delete';

    public static function for(string $method, string $path): self
    {
        foreach (self::cases() as $Action) {
            if ($Action->convention()->supports($method, $path)) {
                return $Action;
            }
        }

        throw new InvalidArgumentException('Unsupported HTTP method '.$method.'.');
    }

    public function matches(string $operationId): bool
    {
        return $this->convention()->matches($operationId);
    }

    private function convention(): ActionConvention
    {
        return $this->enumAttribute(ActionConvention::class);
    }
}
