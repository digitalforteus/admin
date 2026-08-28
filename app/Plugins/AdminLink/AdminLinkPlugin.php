<?php

namespace App\Plugins\AdminLink;

use App\Plugins\DescribesPlugin;
use App\Plugins\RouteTags;
use App\Plugins\TaggedRoute;

/** Collects every tagged route into the ordered list the admin link index renders. */
final readonly class AdminLinkPlugin implements DescribesPlugin
{
    public const string order = 'order';

    public static function attribute(): string
    {
        return AdminLink::class;
    }

    /** @return list<array{name: string, url: string}> */
    public static function routes(): array
    {
        /** @var array<int, list<array{name: string, url: string}>> $ordered */
        $ordered = [];

        foreach (RouteTags::registered(self::attribute()) as $TaggedRoute) {
            $ordered[self::order($TaggedRoute)][] = [
                self::name => $TaggedRoute->name,
                self::url => $TaggedRoute->url,
            ];
        }

        ksort($ordered);

        return array_merge(...array_values($ordered));
    }

    public static function order(TaggedRoute $TaggedRoute): int
    {
        /** @var AdminLink $AdminLink */
        $AdminLink = $TaggedRoute->attribute;

        return $AdminLink->order ?? PHP_INT_MAX;
    }
}
