<?php

namespace App\Routes;

use App\Helpers\RendersRoute;

/**
 * The paths served inside one organization, and inside one of its connections.
 *
 * Which index a path belongs to follows what guards it: these are the ones bound
 * behind authentication and the middleware that resolves the context their first
 * placeholder names, so a path declared here that is bound anywhere else is served
 * with no context resolved and no membership checked. The context is the address
 * rather than anything remembered, which is what makes every page linkable and
 * every switch a plain navigation; what is remembered is only where to send a
 * caller who asked for nothing in particular. Nothing here is ever advertised,
 * because an organization's existence is not public — a caller who is not a member
 * is told the path does not exist rather than that they may not have it. The case
 * whose last segment is a placeholder matches anything, so it is declared last and
 * bound last, and a literal path added after it is unreachable.
 */
enum OrganizationRoute: string
{
    use RendersRoute;

    public const string prefix = '/o';
    public const string organizationParameter = 'organization';
    public const string connectionParameter = 'connection';
    public const string memberParameter = 'member';
    public const string invitationParameter = 'invitation';
    public const string projectParameter = 'project';

    case index = self::prefix.'/{'.self::organizationParameter.'}';
    case connections = self::prefix.'/{'.self::organizationParameter.'}/connections';
    case connectionCreate = self::prefix.'/{'.self::organizationParameter.'}/connections/new';
    case connectionEnabled = self::prefix.'/{'.self::organizationParameter.'}/connections/{'.self::connectionParameter.'}/enabled';
    case connectionVerify = self::prefix.'/{'.self::organizationParameter.'}/connections/{'.self::connectionParameter.'}/verify';
    case connectionManage = self::prefix.'/{'.self::organizationParameter.'}/connections/{'.self::connectionParameter.'}';
    case members = self::prefix.'/{'.self::organizationParameter.'}/members';
    case member = self::prefix.'/{'.self::organizationParameter.'}/members/{'.self::memberParameter.'}';
    case invitations = self::prefix.'/{'.self::organizationParameter.'}/invitations';
    case invitation = self::prefix.'/{'.self::organizationParameter.'}/invitations/{'.self::invitationParameter.'}';
    case projects = self::prefix.'/{'.self::organizationParameter.'}/projects';
    case projectCreate = self::prefix.'/{'.self::organizationParameter.'}/projects/new';
    case project = self::prefix.'/{'.self::organizationParameter.'}/p/{'.self::projectParameter.'}';
    case projectSettings = self::prefix.'/{'.self::organizationParameter.'}/p/{'.self::projectParameter.'}/settings';
    case projectIcon = self::prefix.'/{'.self::organizationParameter.'}/p/{'.self::projectParameter.'}/icon';
    case settings = self::prefix.'/{'.self::organizationParameter.'}/settings';
    case connection = self::prefix.'/{'.self::organizationParameter.'}/{'.self::connectionParameter.'}';
}
