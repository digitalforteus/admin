<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Helpers\SvgName;
use App\Models\Connection;
use App\Models\Organization;
use App\Modules\Connections\ConnectionProvider;
use App\Modules\Connections\ConnectionQuery;
use App\Modules\Organizations\OrganizationContext;
use App\Routes\OrganizationRoute;
use Zerotoprod\DataModel\Describe;

#[Describe([Describe::nullable => true])]
readonly class ConnectionBreadcrumb
{
    use DataModel;

    public const string organization = 'organization';

    #[Describe([Describe::required => true])]
    public string $organization;

    public const string slug = 'slug';

    #[Describe([Describe::required => true])]
    public string $slug;

    public const string active = 'active';

    public ?string $active;

    public const string connections = 'connections';

    /** @var list<array<string, mixed>> */
    #[Describe([Describe::default => []])]
    public array $connections;

    public static function current(): ?self
    {
        $Organization = OrganizationContext::organization();

        if (! $Organization instanceof Organization) {
            return null;
        }

        $Connection = OrganizationContext::connection();

        return self::from([
            self::organization => $Organization->name,
            self::slug => $Organization->slug,
            self::active => $Connection?->name,
            self::connections => array_map(
                static fn (Connection $Each): array => [
                    NavItem::label => $Each->name,
                    NavItem::icon => ConnectionProvider::pluginFor($Each->provider)?->icon() ?? SvgName::link,
                    NavItem::route => OrganizationRoute::connection,
                    NavItem::parameters => [
                        OrganizationRoute::organizationParameter => $Organization->slug,
                        OrganizationRoute::connectionParameter => $Each->slug,
                    ],
                ],
                ConnectionQuery::enabledFor($Organization),
            ),
        ]);
    }

    /** @return array<string, mixed> */
    public function props(): array
    {
        return $this->collect()->all();
    }

    /** @return list<NavItem> */
    public function items(): array
    {
        return array_map(static fn (array $item): NavItem => NavItem::from($item), $this->connections);
    }

    public function isActive(NavItem $NavItem): bool
    {
        return $NavItem->label === $this->active;
    }

    public function url(): string
    {
        return OrganizationRoute::index->url([OrganizationRoute::organizationParameter => $this->slug]);
    }

    public function settingsUrl(): string
    {
        return OrganizationRoute::connections->url([OrganizationRoute::organizationParameter => $this->slug]);
    }
}
