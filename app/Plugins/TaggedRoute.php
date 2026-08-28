<?php

namespace App\Plugins;

/**
 * One route case a plugin's attribute was found on.
 *
 * What is carried is a name and a resolved address rather than the case, so a
 * tagged path cannot hold a parameter — there would be nothing to fill it in with.
 * The attribute instance travels with them: it is the plugin's own data, and only
 * the plugin that declared it knows what to read off it.
 */
final readonly class TaggedRoute
{
    public function __construct(
        public string $name,
        public string $url,
        public object $attribute,
    ) {}
}
