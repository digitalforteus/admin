<?php

namespace App\Routes;

use App\Helpers\RendersRoute;

/**
 * The paths served inside one enterprise.
 *
 * Which index a path belongs to follows what guards it: these are the ones bound
 * behind authentication and the middleware that resolves the enterprise the first
 * placeholder names, so a path declared here and bound anywhere else is served with
 * no enterprise resolved and no standing checked. No standing is held here — it is
 * read from the organizations inside, so an enterprise holding none the caller
 * belongs to is told to be absent rather than forbidden, because its existence is
 * not public either. The case whose segment is a placeholder matches anything, so
 * every literal path is declared before it and bound before it, and one added after
 * it is unreachable.
 */
enum EnterpriseRoute: string
{
    use RendersRoute;

    public const string prefix = '/e';
    public const string enterpriseParameter = 'enterprise';

    case create = self::prefix.'/new';
    case settings = self::prefix.'/{'.self::enterpriseParameter.'}/settings';
    case organizationCreate = self::prefix.'/{'.self::enterpriseParameter.'}/organizations/new';
    case index = self::prefix.'/{'.self::enterpriseParameter.'}';
}
