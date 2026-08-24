<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Helpers\OrganizationRole;
use App\Helpers\SvgName;
use App\Models\Connection;
use App\Models\Enterprise;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Modules\Connections\ConnectionQuery;
use App\Modules\Enterprises\EnterpriseContext;
use App\Modules\Enterprises\EnterpriseForm;
use App\Modules\Enterprises\EnterpriseQuery;
use App\Modules\Organizations\MembershipQuery;
use App\Modules\Organizations\OrganizationContext;
use App\Modules\Projects\ProjectForm;
use App\Modules\Projects\ProjectQuery;
use App\Modules\Settings\Organizations\OrganizationForm;
use App\Routes\EnterpriseRoute;
use App\Routes\OrganizationRoute;
use Illuminate\Support\Facades\Auth;
use Zerotoprod\DataModel\Describe;

#[Describe([Describe::nullable => true])]
readonly class Breadcrumb
{
    use DataModel;

    public const string segments = 'segments';

    /** @var list<array<string, mixed>> */
    #[Describe([Describe::default => []])]
    public array $segments;

    public static function current(): ?self
    {
        $User = Auth::user();

        if (! $User instanceof User) {
            return null;
        }

        return self::from([self::segments => self::cascade($User)]);
    }

    /** @return list<array<string, mixed>> */
    private static function cascade(User $User): array
    {
        $Enterprise = EnterpriseContext::enterprise();

        if (! $Enterprise instanceof Enterprise) {
            return [self::unsettledEnterprise($User)];
        }

        $segments = [self::enterprise($Enterprise, $User)];
        $Organization = OrganizationContext::organization();

        if (! $Organization instanceof Organization) {
            return [...$segments, self::unsettledOrganization($Enterprise, $User)];
        }

        $segments[] = self::organization($Organization, $Enterprise, $User);
        $Project = OrganizationContext::project();

        if (! $Project instanceof Project) {
            return [...$segments, self::unsettledProject($Organization, $User)];
        }

        $segments[] = self::project($Project, $Organization, $User);
        $Connection = OrganizationContext::connection();

        if (! $Connection instanceof Connection) {
            return [...$segments, self::unsettledConnection($Organization, $Project, $User)];
        }

        return [...$segments, self::connection($Connection, $Project, $Organization, $User)];
    }

    /** @return array<string, mixed> */
    private static function unsettledEnterprise(User $User): array
    {
        return [
            BreadcrumbSegment::label => 'Select enterprise',
            BreadcrumbSegment::url => null,
            BreadcrumbSegment::picture => null,
            BreadcrumbSegment::fallback => SvgName::city,
            BreadcrumbSegment::switchLabel => 'Enterprises',
            BreadcrumbSegment::settingsUrl => null,
            BreadcrumbSegment::settingsLabel => '',
            BreadcrumbSegment::createUrl => null,
            BreadcrumbSegment::createAction => EnterpriseRoute::create->url(),
            BreadcrumbSegment::createFields => [
                BreadcrumbField::of(EnterpriseForm::textInput(EnterpriseForm::name)),
                BreadcrumbField::of(EnterpriseForm::textInput(EnterpriseForm::organization)),
            ],
            BreadcrumbSegment::createLabel => 'New enterprise',
            BreadcrumbSegment::items => array_map(
                static fn (Enterprise $Each): array => [
                    BreadcrumbItem::label => $Each->name,
                    BreadcrumbItem::url => EnterpriseRoute::index->url([
                        EnterpriseRoute::enterpriseParameter => $Each->slug,
                    ]),
                    BreadcrumbItem::picture => null,
                    BreadcrumbItem::fallback => SvgName::city,
                ],
                EnterpriseQuery::forUser($User),
            ),
        ];
    }

    /** @return array<string, mixed> */
    private static function unsettledOrganization(Enterprise $Enterprise, User $User): array
    {
        return [
            BreadcrumbSegment::label => 'Select organization',
            BreadcrumbSegment::url => null,
            BreadcrumbSegment::picture => null,
            BreadcrumbSegment::fallback => SvgName::building,
            BreadcrumbSegment::switchLabel => 'Organizations',
            BreadcrumbSegment::settingsUrl => null,
            BreadcrumbSegment::settingsLabel => '',
            BreadcrumbSegment::createUrl => null,
            BreadcrumbSegment::createAction => EnterpriseRoute::organizationCreate->url([
                EnterpriseRoute::enterpriseParameter => $Enterprise->slug,
            ]),
            BreadcrumbSegment::createFields => [
                BreadcrumbField::of(OrganizationForm::textInput(OrganizationForm::name)),
            ],
            BreadcrumbSegment::createLabel => 'New organization',
            BreadcrumbSegment::items => array_map(
                static fn (Organization $Each): array => [
                    BreadcrumbItem::label => $Each->name,
                    BreadcrumbItem::url => OrganizationRoute::index->url([
                        OrganizationRoute::organizationParameter => $Each->slug,
                    ]),
                    BreadcrumbItem::picture => $Each->iconUrl(),
                    BreadcrumbItem::fallback => SvgName::building,
                ],
                EnterpriseQuery::organizations($Enterprise, $User),
            ),
        ];
    }

    /** @return array<string, mixed> */
    private static function unsettledProject(Organization $Organization, User $User): array
    {
        $parameters = [OrganizationRoute::organizationParameter => $Organization->slug];
        $manages = MembershipQuery::role($Organization, $User)?->manages() ?? false;

        return [
            BreadcrumbSegment::label => 'Select project',
            BreadcrumbSegment::url => null,
            BreadcrumbSegment::picture => null,
            BreadcrumbSegment::fallback => SvgName::folder,
            BreadcrumbSegment::switchLabel => 'Projects',
            BreadcrumbSegment::settingsUrl => null,
            BreadcrumbSegment::settingsLabel => '',
            BreadcrumbSegment::createUrl => null,
            BreadcrumbSegment::createAction => self::projectAction($manages, $parameters),
            BreadcrumbSegment::createFields => [
                BreadcrumbField::of(ProjectForm::textInput(ProjectForm::name)),
            ],
            BreadcrumbSegment::createLabel => 'New project',
            BreadcrumbSegment::items => array_map(
                static fn (Project $Each): array => [
                    BreadcrumbItem::label => $Each->name,
                    BreadcrumbItem::url => OrganizationRoute::project->url([
                        ...$parameters,
                        OrganizationRoute::projectParameter => $Each->slug,
                    ]),
                    BreadcrumbItem::picture => $Each->iconUrl(),
                    BreadcrumbItem::fallback => SvgName::folder,
                ],
                ProjectQuery::forOrganization($Organization),
            ),
        ];
    }

    /** @return array<string, mixed> */
    private static function unsettledConnection(Organization $Organization, Project $Project, User $User): array
    {
        $parameters = [
            OrganizationRoute::organizationParameter => $Organization->slug,
            OrganizationRoute::projectParameter => $Project->slug,
        ];
        $owns = MembershipQuery::role($Organization, $User) === OrganizationRole::owner;

        return [
            BreadcrumbSegment::label => 'Select connection',
            BreadcrumbSegment::url => null,
            BreadcrumbSegment::picture => null,
            BreadcrumbSegment::fallback => SvgName::link,
            BreadcrumbSegment::switchLabel => 'Connections',
            BreadcrumbSegment::settingsUrl => null,
            BreadcrumbSegment::settingsLabel => '',
            BreadcrumbSegment::createUrl => self::connectionCreate($owns, $parameters),
            BreadcrumbSegment::createAction => null,
            BreadcrumbSegment::createFields => [],
            BreadcrumbSegment::createLabel => 'New connection',
            BreadcrumbSegment::items => array_map(
                static fn (Connection $Each): array => [
                    BreadcrumbItem::label => $Each->name,
                    BreadcrumbItem::url => OrganizationRoute::connection->url([
                        ...$parameters,
                        OrganizationRoute::connectionParameter => $Each->slug,
                    ]),
                    BreadcrumbItem::picture => null,
                    BreadcrumbItem::fallback => SvgName::link,
                ],
                ConnectionQuery::enabledFor($Project),
            ),
        ];
    }

    /** @return list<BreadcrumbSegment> */
    public function trail(): array
    {
        return array_map(
            static fn (array $segment): BreadcrumbSegment => BreadcrumbSegment::from($segment),
            $this->segments,
        );
    }

    /** @return array<string, mixed> */
    public function props(): array
    {
        return $this->collect()->all();
    }

    /** @return array<string, mixed> */
    private static function enterprise(Enterprise $Enterprise, User $User): array
    {
        $parameters = [EnterpriseRoute::enterpriseParameter => $Enterprise->slug];

        return [
            BreadcrumbSegment::label => $Enterprise->name,
            BreadcrumbSegment::url => EnterpriseRoute::index->url($parameters),
            BreadcrumbSegment::picture => null,
            BreadcrumbSegment::fallback => SvgName::city,
            BreadcrumbSegment::switchLabel => 'Switch enterprise',
            BreadcrumbSegment::settingsUrl => self::enterpriseSettings($Enterprise, $User, $parameters),
            BreadcrumbSegment::settingsLabel => 'Enterprise settings',
            BreadcrumbSegment::createUrl => EnterpriseRoute::create->url(),
            BreadcrumbSegment::createLabel => 'New enterprise',
            BreadcrumbSegment::items => array_map(
                static fn (Enterprise $Each): array => [
                    BreadcrumbItem::label => $Each->name,
                    BreadcrumbItem::url => EnterpriseRoute::index->url([
                        EnterpriseRoute::enterpriseParameter => $Each->slug,
                    ]),
                    BreadcrumbItem::picture => null,
                    BreadcrumbItem::fallback => SvgName::city,
                ],
                self::beside(EnterpriseQuery::forUser($User), $Enterprise->slug),
            ),
        ];
    }

    /** @return array<string, mixed> */
    private static function organization(Organization $Organization, Enterprise $Enterprise, User $User): array
    {
        $parameters = [OrganizationRoute::organizationParameter => $Organization->slug];

        return [
            BreadcrumbSegment::label => $Organization->name,
            BreadcrumbSegment::url => OrganizationRoute::index->url($parameters),
            BreadcrumbSegment::picture => $Organization->iconUrl(),
            BreadcrumbSegment::fallback => SvgName::building,
            BreadcrumbSegment::switchLabel => 'Switch organization',
            BreadcrumbSegment::settingsUrl => self::organizationSettings($Organization, $User, $parameters),
            BreadcrumbSegment::settingsLabel => 'Organization settings',
            BreadcrumbSegment::createUrl => EnterpriseRoute::organizationCreate->url([
                EnterpriseRoute::enterpriseParameter => $Enterprise->slug,
            ]),
            BreadcrumbSegment::createLabel => 'New organization',
            BreadcrumbSegment::items => array_map(
                static fn (Organization $Each): array => [
                    BreadcrumbItem::label => $Each->name,
                    BreadcrumbItem::url => OrganizationRoute::index->url([
                        OrganizationRoute::organizationParameter => $Each->slug,
                    ]),
                    BreadcrumbItem::picture => $Each->iconUrl(),
                    BreadcrumbItem::fallback => SvgName::building,
                ],
                self::beside(EnterpriseQuery::organizations($Enterprise, $User), $Organization->slug),
            ),
        ];
    }

    /**
     * What stands beside the thing being looked at, which is everything but itself.
     *
     * @template TModel of Enterprise|Organization|Project|Connection
     *
     * @param  list<TModel>  $models
     * @return list<TModel>
     */
    private static function beside(array $models, string $slug): array
    {
        return array_values(array_filter(
            $models,
            static fn (Enterprise|Organization|Project|Connection $Model): bool => $Model->slug !== $slug,
        ));
    }

    /** @param  array<string, string|int>  $parameters */
    private static function enterpriseSettings(Enterprise $Enterprise, User $User, array $parameters): ?string
    {
        if (! EnterpriseQuery::manages($Enterprise, $User)) {
            return null;
        }

        return EnterpriseRoute::settings->url($parameters);
    }

    /** @param  array<string, string|int>  $parameters */
    private static function organizationSettings(Organization $Organization, User $User, array $parameters): ?string
    {
        if (MembershipQuery::role($Organization, $User) !== OrganizationRole::owner) {
            return null;
        }

        return OrganizationRoute::settings->url($parameters);
    }

    /** @return array<string, mixed> */
    private static function project(Project $Project, Organization $Organization, User $User): array
    {
        $parameters = [OrganizationRoute::organizationParameter => $Organization->slug];
        $manages = MembershipQuery::role($Organization, $User)?->manages() ?? false;

        return [
            BreadcrumbSegment::label => $Project->name,
            BreadcrumbSegment::url => OrganizationRoute::project->url([
                ...$parameters,
                OrganizationRoute::projectParameter => $Project->slug,
            ]),
            BreadcrumbSegment::picture => $Project->iconUrl(),
            BreadcrumbSegment::fallback => SvgName::folder,
            BreadcrumbSegment::switchLabel => 'Switch project',
            BreadcrumbSegment::settingsUrl => self::projectSettings($manages, [
                ...$parameters,
                OrganizationRoute::projectParameter => $Project->slug,
            ]),
            BreadcrumbSegment::settingsLabel => 'Project settings',
            BreadcrumbSegment::createUrl => self::projectCreate($manages, $parameters),
            BreadcrumbSegment::createLabel => 'New project',
            BreadcrumbSegment::items => array_map(
                static fn (Project $Each): array => [
                    BreadcrumbItem::label => $Each->name,
                    BreadcrumbItem::url => OrganizationRoute::project->url([
                        ...$parameters,
                        OrganizationRoute::projectParameter => $Each->slug,
                    ]),
                    BreadcrumbItem::picture => $Each->iconUrl(),
                    BreadcrumbItem::fallback => SvgName::folder,
                ],
                self::beside(ProjectQuery::forOrganization($Organization), $Project->slug),
            ),
        ];
    }

    /** @param  array<string, string|int>  $parameters */
    private static function projectSettings(bool $manages, array $parameters): ?string
    {
        if (! $manages) {
            return null;
        }

        return OrganizationRoute::projectSettings->url($parameters);
    }

    /** @param  array<string, string|int>  $parameters */
    private static function projectAction(bool $manages, array $parameters): ?string
    {
        if (! $manages) {
            return null;
        }

        return OrganizationRoute::projects->url($parameters);
    }

    /** @param  array<string, string|int>  $parameters */
    private static function projectCreate(bool $manages, array $parameters): ?string
    {
        if (! $manages) {
            return null;
        }

        return OrganizationRoute::projectCreate->url($parameters);
    }

    /** @return array<string, mixed> */
    private static function connection(Connection $Connection, Project $Project, Organization $Organization, User $User): array
    {
        $parameters = [
            OrganizationRoute::organizationParameter => $Organization->slug,
            OrganizationRoute::projectParameter => $Project->slug,
        ];
        $owns = MembershipQuery::role($Organization, $User) === OrganizationRole::owner;

        return [
            BreadcrumbSegment::label => $Connection->name,
            BreadcrumbSegment::url => OrganizationRoute::connection->url([
                ...$parameters,
                OrganizationRoute::connectionParameter => $Connection->slug,
            ]),
            BreadcrumbSegment::picture => null,
            BreadcrumbSegment::fallback => SvgName::link,
            BreadcrumbSegment::switchLabel => 'Switch connection',
            BreadcrumbSegment::settingsUrl => self::connectionSettings($owns, [
                ...$parameters,
                OrganizationRoute::connectionParameter => $Connection->slug,
            ]),
            BreadcrumbSegment::settingsLabel => 'Connection settings',
            BreadcrumbSegment::createUrl => self::connectionCreate($owns, $parameters),
            BreadcrumbSegment::createLabel => 'New connection',
            BreadcrumbSegment::items => array_map(
                static fn (Connection $Each): array => [
                    BreadcrumbItem::label => $Each->name,
                    BreadcrumbItem::url => OrganizationRoute::connection->url([
                        ...$parameters,
                        OrganizationRoute::connectionParameter => $Each->slug,
                    ]),
                    BreadcrumbItem::picture => null,
                    BreadcrumbItem::fallback => SvgName::link,
                ],
                self::beside(ConnectionQuery::enabledFor($Project), $Connection->slug),
            ),
        ];
    }

    /** @param  array<string, string|int>  $parameters */
    private static function connectionSettings(bool $owns, array $parameters): ?string
    {
        if (! $owns) {
            return null;
        }

        return OrganizationRoute::connectionManage->url($parameters);
    }

    /** @param  array<string, string|int>  $parameters */
    private static function connectionCreate(bool $owns, array $parameters): ?string
    {
        if (! $owns) {
            return null;
        }

        return OrganizationRoute::connectionCreate->url($parameters);
    }
}
