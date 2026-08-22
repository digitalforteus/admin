<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Helpers\SvgName;
use App\Modules\Connections\ConnectionPlugin;
use App\Modules\Connections\ConnectionProvider;
use App\Routes\OrganizationRoute;
use Zerotoprod\DataModel\Describe;

readonly class ConnectionRow
{
    use DataModel;

    public const string organization = 'organization';

    #[Describe([Describe::required => true])]
    public string $organization;

    public const string name = 'name';

    #[Describe([Describe::required => true])]
    public string $name;

    public const string slug = 'slug';

    #[Describe([Describe::required => true])]
    public string $slug;

    public const string provider = 'provider';

    #[Describe([Describe::required => true])]
    public string $provider;

    public const string enabled = 'enabled';

    #[Describe([Describe::default => false])]
    public bool $enabled;

    public function available(): bool
    {
        return $this->plugin() instanceof ConnectionPlugin;
    }

    public function label(): string
    {
        return $this->plugin()?->label() ?? $this->provider;
    }

    public function icon(): SvgName
    {
        return $this->plugin()?->icon() ?? SvgName::link;
    }

    /** @return array<string, mixed> */
    public function svg(): array
    {
        return [Svg::name => $this->icon(), Svg::classname => 'h-4 w-4 opacity-70'];
    }

    public function url(): string
    {
        return OrganizationRoute::connection->url([
            OrganizationRoute::organizationParameter => $this->organization,
            OrganizationRoute::connectionParameter => $this->slug,
        ]);
    }

    public function manageUrl(): string
    {
        return OrganizationRoute::connectionManage->url([
            OrganizationRoute::organizationParameter => $this->organization,
            OrganizationRoute::connectionParameter => $this->slug,
        ]);
    }

    public function enabledUrl(): string
    {
        return OrganizationRoute::connectionEnabled->url([
            OrganizationRoute::organizationParameter => $this->organization,
            OrganizationRoute::connectionParameter => $this->slug,
        ]);
    }

    private function plugin(): ?ConnectionPlugin
    {
        return ConnectionProvider::pluginFor($this->provider);
    }
}
