<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Helpers\OrganizationRole;
use App\Helpers\SvgName;
use App\Models\Connection;
use App\Models\Enterprise;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Connections\ConnectionQuery;
use App\Modules\Enterprises\EnterpriseContext;
use App\Modules\Enterprises\EnterpriseQuery;
use App\Modules\Organizations\MembershipQuery;
use App\Modules\Organizations\OrganizationContext;
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

        $segments = array_values(array_filter([
            self::enterprise($User),
            self::organization($User),
            self::connection(),
        ]));

        return $segments === [] ? null : self::from([self::segments => $segments]);
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

    /** @return array<string, mixed>|null */
    private static function enterprise(User $User): ?array
    {
        $Enterprise = EnterpriseContext::enterprise();

        if (! $Enterprise instanceof Enterprise) {
            return null;
        }

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

    /** @return array<string, mixed>|null */
    private static function organization(User $User): ?array
    {
        $Organization = OrganizationContext::organization();
        $Enterprise = EnterpriseContext::enterprise();

        if (! $Organization instanceof Organization || ! $Enterprise instanceof Enterprise) {
            return null;
        }

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
     * @template TModel of Enterprise|Organization|Connection
     *
     * @param  list<TModel>  $models
     * @return list<TModel>
     */
    private static function beside(array $models, string $slug): array
    {
        return array_values(array_filter(
            $models,
            static fn (Enterprise|Organization|Connection $Model): bool => $Model->slug !== $slug,
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

    /** @return array<string, mixed>|null */
    private static function connection(): ?array
    {
        $Organization = OrganizationContext::organization();
        $Connection = OrganizationContext::connection();

        if (! $Organization instanceof Organization || ! $Connection instanceof Connection) {
            return null;
        }

        $parameters = [OrganizationRoute::organizationParameter => $Organization->slug];

        return [
            BreadcrumbSegment::label => $Connection->name,
            BreadcrumbSegment::url => OrganizationRoute::connection->url([
                ...$parameters,
                OrganizationRoute::connectionParameter => $Connection->slug,
            ]),
            BreadcrumbSegment::picture => null,
            BreadcrumbSegment::fallback => SvgName::link,
            BreadcrumbSegment::switchLabel => 'Switch connection',
            BreadcrumbSegment::settingsUrl => OrganizationRoute::connectionManage->url([
                ...$parameters,
                OrganizationRoute::connectionParameter => $Connection->slug,
            ]),
            BreadcrumbSegment::settingsLabel => 'Connection settings',
            BreadcrumbSegment::createUrl => OrganizationRoute::connectionCreate->url($parameters),
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
                self::beside(ConnectionQuery::enabledFor($Organization), $Connection->slug),
            ),
        ];
    }
}
