<?php

namespace App\Plugins;

use App\Plugins\AdminLink\AdminLinkPlugin;

/**
 * The plugins this application installs, one case per plugin.
 *
 * A case here is the whole of installing one, and a page asks the registry rather
 * than naming the class: the registry is the declaration, so answering touches no
 * filesystem and loads nothing to do it. A plugin this does not name is not
 * installed, wherever its directory lives.
 */
enum PluginIndex: string
{
    case adminLink = AdminLinkPlugin::class;

    /** @return class-string<DescribesPlugin> */
    public function plugin(): string
    {
        /** @var class-string<DescribesPlugin> */
        return $this->value;
    }

    /** @return list<array{name: string, url: string}> */
    public function routes(): array
    {
        return $this->plugin()::routes();
    }
}
