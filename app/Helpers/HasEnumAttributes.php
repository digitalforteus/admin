<?php

namespace App\Helpers;

use ReflectionAttribute;
use ReflectionEnumUnitCase;
use UnitEnum;

/** @phpstan-require-implements UnitEnum */
trait HasEnumAttributes
{
    /**
     * @template TAttribute of object
     *
     * @param  class-string<TAttribute>  $attribute
     * @return TAttribute
     */
    private function enumAttribute(string $attribute): object
    {
        /** @var ReflectionAttribute<TAttribute> $ReflectionAttribute */
        $ReflectionAttribute = new ReflectionEnumUnitCase(self::class, $this->name)
            ->getAttributes($attribute)[0];

        return $ReflectionAttribute->newInstance();
    }
}
