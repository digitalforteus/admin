<?php

namespace App\Plugins;

/**
 * What a plugin contributes and how it is read.
 *
 * A plugin owns one class-constant attribute and the meaning of tagging a route
 * case with it. The host sweeps the registered route indexes, so an implementation
 * declares the attribute rather than reflecting anything itself, and returns the
 * links a page renders. The keys of that list are named here because both ends —
 * the plugin writing them and the page reading them — spell the same shape.
 */
interface DescribesPlugin
{
    public const string name = 'name';
    public const string url = 'url';

    /** @return class-string the attribute a route case is tagged with */
    public static function attribute(): string;

    /** @return list<array{name: string, url: string}> */
    public static function routes(): array;
}
