<?php

namespace App\Routes;

use App\Helpers\Depth;
use App\Helpers\RendersRoute;
use App\Modules\Contexts\Context;
use Illuminate\Database\Eloquent\Model;
use ReflectionEnumBackedCase;

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

    #[ContextRouteFor(Depth::enterprise, ContextRouteRole::collection)]
    case enterpriseIndex = self::enterprises;

    #[ContextRouteFor(Depth::enterprise, ContextRouteRole::create)]
    case enterpriseCreate = self::enterprises.'/new';

    #[ContextRouteFor(Depth::enterprise, ContextRouteRole::of)]
    case enterprise = self::oneEnterprise;

    #[ContextRouteFor(Depth::enterprise, ContextRouteRole::settings)]
    case enterpriseSettings = self::oneEnterprise.'/settings';

    #[ContextRouteFor(Depth::organization, ContextRouteRole::collection)]
    case organizationIndex = self::organizations;

    #[ContextRouteFor(Depth::organization, ContextRouteRole::create)]
    case organizationCreate = self::organizations.'/new';

    #[ContextRouteFor(Depth::organization, ContextRouteRole::of)]
    case organization = self::oneOrganization;

    #[ContextRouteFor(Depth::organization, ContextRouteRole::settings)]
    case organizationSettings = self::oneOrganization.'/settings';

    case organizationIcon = self::oneOrganization.'/icon';
    case members = self::oneOrganization.'/members';
    case member = self::oneOrganization.'/members/{'.self::memberParameter.'}';
    case invitations = self::oneOrganization.'/invitations';
    case invitation = self::oneOrganization.'/invitations/{'.self::invitationParameter.'}';

    #[ContextRouteFor(Depth::project, ContextRouteRole::collection)]
    case projectIndex = self::projects;

    #[ContextRouteFor(Depth::project, ContextRouteRole::create)]
    case projectCreate = self::projects.'/new';

    #[ContextRouteFor(Depth::project, ContextRouteRole::of)]
    case project = self::oneProject;

    #[ContextRouteFor(Depth::project, ContextRouteRole::settings)]
    case projectSettings = self::oneProject.'/settings';

    case projectIcon = self::oneProject.'/icon';

    #[ContextRouteFor(Depth::connection, ContextRouteRole::collection)]
    case connectionIndex = self::connections;

    #[ContextRouteFor(Depth::connection, ContextRouteRole::create)]
    case connectionCreate = self::connections.'/new';

    #[ContextRouteFor(Depth::connection, ContextRouteRole::of)]
    case connection = self::oneConnection;

    #[ContextRouteFor(Depth::connection, ContextRouteRole::settings)]
    case connectionSettings = self::oneConnection.'/settings';

    case connectionEnabled = self::oneConnection.'/enabled';
    case connectionVerify = self::oneConnection.'/verify';

    /** The path one subject of a depth is addressed at. */
    public static function of(Depth $Depth): self
    {
        return self::tagged($Depth, ContextRouteRole::of);
    }

    /** The path every subject of a depth is listed at, inside the one containing them. */
    public static function collection(Depth $Depth): self
    {
        return self::tagged($Depth, ContextRouteRole::collection);
    }

    /** The form a new subject of a depth is named on. */
    public static function create(Depth $Depth): self
    {
        return self::tagged($Depth, ContextRouteRole::create);
    }

    /** The path a subject of a depth is configured at. */
    public static function settings(Depth $Depth): self
    {
        return self::tagged($Depth, ContextRouteRole::settings);
    }

    /** The one case tagged as the given role for the given depth. */
    private static function tagged(Depth $Depth, ContextRouteRole $ContextRouteRole): self
    {
        $matches = array_values(array_filter(
            self::cases(),
            static fn (self $Case): bool => self::taggedAs($Case, $Depth, $ContextRouteRole),
        ));

        /** @var self $Match Every depth carries a case for every role, so a match always exists. */
        $Match = $matches[0];

        return $Match;
    }

    private static function taggedAs(self $self, Depth $Depth, ContextRouteRole $ContextRouteRole): bool
    {
        $Attributes = new ReflectionEnumBackedCase(self::class, $self->name)->getAttributes(ContextRouteFor::class);

        if ($Attributes === []) {
            return false;
        }

        $For = $Attributes[0]->newInstance();

        return $For->depth === $Depth && $For->role === $ContextRouteRole;
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
