<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Helpers\SvgName;
use App\Modules\Connections\ConnectionPlugin;
use App\Modules\Connections\ConnectionProvider;
use App\Routes\ContextRoute;
use Zerotoprod\DataModel\Describe;

readonly class ConnectionRow
{
    use DataModel;

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
        return ContextRoute::connection->url($this->parameters());
    }

    public function manageUrl(): string
    {
        return ContextRoute::connectionSettings->url($this->parameters());
    }

    public function enabledUrl(): string
    {
        return ContextRoute::connectionEnabled->url($this->parameters());
    }

    /** @return array<string, string|int> */
    private function parameters(): array
    {
        return ContextRoute::parameters([ContextRoute::connectionParameter => $this->slug]);
    }

    private function plugin(): ?ConnectionPlugin
    {
        return ConnectionProvider::pluginFor($this->provider);
    }
}
