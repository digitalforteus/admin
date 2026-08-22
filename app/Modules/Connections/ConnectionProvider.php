<?php

namespace App\Modules\Connections;

use App\Modules\Connections\Github\GithubConnection;

/**
 * The connection providers this application serves, one case per plugin.
 *
 * A case here is the whole of registering a provider: the case name is the key a
 * stored row holds, and the case value is the class that answers for it, so
 * resolving a key loads one class and touches no filesystem. A key this does not
 * name is not a provider of this application, wherever its code lives — and that is
 * the point, because a row naming one is expected and must degrade rather than
 * throw. Storing the name and not the value is what keeps a class rename off the
 * database; every lookup returns nothing for an unknown key, and every caller is
 * required to handle that.
 */
enum ConnectionProvider: string
{
    case github = GithubConnection::class;

    public static function tryFromKey(?string $provider): ?self
    {
        foreach (self::cases() as $Case) {
            if ($Case->name === $provider) {
                return $Case;
            }
        }

        return null;
    }

    public static function pluginFor(?string $provider): ?ConnectionPlugin
    {
        return self::tryFromKey($provider)?->plugin();
    }

    public function plugin(): ConnectionPlugin
    {
        /** @var class-string<ConnectionPlugin> $plugin */
        $plugin = $this->value;

        return new $plugin;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_column(self::cases(), 'name');
    }
}
