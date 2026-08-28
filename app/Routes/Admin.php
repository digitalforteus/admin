<?php

namespace App\Routes;

use App\Helpers\RendersRoute;
use App\Plugins\AdminLink\AdminLink;

/**
 * The role-gated paths, every one under one prefix.
 *
 * A case is built from that prefix rather than repeating it, because that prefix is
 * what everything here is guarded by: the pages are gated by a pattern over it, and
 * the ones bound in a route file are bound inside a group carrying the same guard, so
 * a path falling outside the prefix is served with no authentication and no role
 * check. There is no sitemap here, so none of these is ever advertised. These pages
 * carry their own navigation, which stands in for the default rail anywhere under the
 * prefix. A placeholder is named by the constant beside it rather than spelled into
 * the path, so what fills it in cannot drift from what the path asks for.
 */
enum Admin: string
{
    use RendersRoute;

    public const string prefix = '/admin';
    public const string userParameter = 'user';
    public const string providerParameter = 'provider';
    public const string sessionParameter = 'session';

    case api_user = self::prefix.'/api/users/{user}';
    case api_user_sessions = self::prefix.'/api/users/{user}/sessions';
    case api_users = self::prefix.'/api/users';
    case content = self::prefix.'/content';
    case index = self::prefix;
    case links = self::prefix.'/links';
    #[AdminLink]
    case openapi = self::prefix.Web::openapi->value;
    case sessions = self::prefix.'/sessions';
    case session = self::prefix.'/sessions/{'.self::sessionParameter.'}';
    case users = self::prefix.'/users';
    case user = self::prefix.'/users/{'.self::userParameter.'}';
    case userSessions = self::prefix.'/users/{'.self::userParameter.'}/sessions';
    case userProvider = self::prefix.'/users/{'.self::userParameter.'}/providers/{'.self::providerParameter.'}';
}
