<?php

namespace App\Routes;

use App\Helpers\Depth;
use App\Helpers\RendersRoute;
use App\Modules\Contexts\Context;
use Illuminate\Database\Eloquent\Model;

/**
 * The paths served inside the hierarchy, one chain of containment.
 *
 * Which index a path belongs to follows what guards it: these are the ones bound behind
 * authentication and the middleware that walks the chain their placeholders name, so a
 * path declared here and bound anywhere else is served with no subject resolved and no
 * standing checked. Every depth is spelled by a literal segment before its placeholder
 * and every placeholder is nested inside the one containing it, so the address is the
 * containment and a segment can never be mistaken for a name — which is what lets a name
 * be unique only where it is addressed. Nothing here is ever advertised, because a
 * subject's existence is not public: a caller holding no standing is told the path does
 * not exist rather than that they may not have it.
 */
enum ContextRoute: string
{
    use RendersRoute;

    public const string enterpriseParameter = 'enterprise';
    public const string organizationParameter = 'organization';
    public const string projectParameter = 'project';
    public const string connectionParameter = 'connection';
    public const string memberParameter = 'member';
    public const string invitationParameter = 'invitation';
    public const string enterprises = '/e';
    public const string oneEnterprise = self::enterprises.'/{'.self::enterpriseParameter.'}';
    public const string organizations = self::oneEnterprise.'/o';
    public const string oneOrganization = self::organizations.'/{'.self::organizationParameter.'}';
    public const string projects = self::oneOrganization.'/p';
    public const string oneProject = self::projects.'/{'.self::projectParameter.'}';
    public const string connections = self::oneProject.'/c';
    public const string oneConnection = self::connections.'/{'.self::connectionParameter.'}';

    case enterpriseIndex = self::enterprises;
    case enterpriseCreate = self::enterprises.'/new';
    case enterprise = self::oneEnterprise;
    case enterpriseSettings = self::oneEnterprise.'/settings';

    case organizationIndex = self::organizations;
    case organizationCreate = self::organizations.'/new';
    case organization = self::oneOrganization;
    case organizationSettings = self::oneOrganization.'/settings';
    case organizationIcon = self::oneOrganization.'/icon';
    case members = self::oneOrganization.'/members';
    case member = self::oneOrganization.'/members/{'.self::memberParameter.'}';
    case invitations = self::oneOrganization.'/invitations';
    case invitation = self::oneOrganization.'/invitations/{'.self::invitationParameter.'}';

    case projectIndex = self::projects;
    case projectCreate = self::projects.'/new';
    case project = self::oneProject;
    case projectSettings = self::oneProject.'/settings';
    case projectIcon = self::oneProject.'/icon';

    case connectionIndex = self::connections;
    case connectionCreate = self::connections.'/new';
    case connection = self::oneConnection;
    case connectionSettings = self::oneConnection.'/settings';
    case connectionEnabled = self::oneConnection.'/enabled';
    case connectionVerify = self::oneConnection.'/verify';

    /** The path one subject of a depth is addressed at. */
    public static function of(Depth $Depth): self
    {
        return match ($Depth) {
            Depth::enterprise => self::enterprise,
            Depth::organization => self::organization,
            Depth::project => self::project,
            Depth::connection => self::connection,
        };
    }

    /** The path every subject of a depth is listed at, inside the one containing them. */
    public static function collection(Depth $Depth): self
    {
        return match ($Depth) {
            Depth::enterprise => self::enterpriseIndex,
            Depth::organization => self::organizationIndex,
            Depth::project => self::projectIndex,
            Depth::connection => self::connectionIndex,
        };
    }

    /** The form a new subject of a depth is named on. */
    public static function create(Depth $Depth): self
    {
        return match ($Depth) {
            Depth::enterprise => self::enterpriseCreate,
            Depth::organization => self::organizationCreate,
            Depth::project => self::projectCreate,
            Depth::connection => self::connectionCreate,
        };
    }

    /** The path a subject of a depth is configured at. */
    public static function settings(Depth $Depth): self
    {
        return match ($Depth) {
            Depth::enterprise => self::enterpriseSettings,
            Depth::organization => self::organizationSettings,
            Depth::project => self::projectSettings,
            Depth::connection => self::connectionSettings,
        };
    }

    /**
     * The placeholders the address has already settled, so nothing rebuilds them.
     *
     * @param  array<string, string|int>  $also
     * @return array<string, string|int>
     */
    public static function parameters(array $also = []): array
    {
        $parameters = [];

        foreach (Depth::chain() as $Depth) {
            $Model = Context::of($Depth);

            if (! $Model instanceof Model) {
                break;
            }

            $slug = $Model->getAttribute('slug');

            if (is_string($slug)) {
                $parameters[$Depth->value] = $slug;
            }
        }

        return [...$parameters, ...$also];
    }
}
