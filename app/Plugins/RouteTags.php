<?php

namespace App\Plugins;

use App\AppConfig;
use BackedEnum;
use ReflectionEnumBackedCase;

/**
 * The host: the one sweep every plugin is answered by.
 *
 * Every registered index is read, so a case is tagged wherever it lives — and one
 * outside them is not this application's routing, so it is not collected. Reading a
 * single enum is the same sweep narrowed, and it reports what that enum holds in
 * the order it declares it: ordering is the job of the plugin, where the indexes meet.
 */
final readonly class RouteTags
{
    /**
     * @param  class-string  $attribute
     * @return list<TaggedRoute>
     */
    public static function registered(string $attribute): array
    {
        $tagged = [];

        foreach (AppConfig::routeIndexes() as $enum) {
            foreach (self::in($enum, $attribute) as $TaggedRoute) {
                $tagged[] = $TaggedRoute;
            }
        }

        return $tagged;
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     * @param  class-string  $attribute
     * @return list<TaggedRoute>
     */
    public static function in(string $enum, string $attribute): array
    {
        $tagged = [];

        foreach ($enum::cases() as $Case) {
            $attributes = new ReflectionEnumBackedCase($enum, $Case->name)->getAttributes($attribute);

            if ($attributes === []) {
                continue;
            }

            $tagged[] = new TaggedRoute($Case->name, (string) $Case->value, $attributes[0]->newInstance());
        }

        return $tagged;
    }
}
