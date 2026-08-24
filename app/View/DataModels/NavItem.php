<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Helpers\SvgName;
use App\Routes\Admin;
use App\Routes\Auth;
use App\Routes\ContextRoute;
use App\Routes\Web;
use Attribute;
use Zerotoprod\DataModel\Describe;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
readonly class NavItem
{
    use DataModel;

    /** @param  array<string, mixed>  $attributes */
    public function __construct(array $attributes = [])
    {
        if ($attributes !== []) {
            self::from($attributes, $this);
        }
    }

    public const string label = 'label';

    #[Describe([Describe::required => true])]
    public string $label;

    public const string icon = 'icon';

    #[Describe([Describe::required => true])]
    public SvgName $icon;

    public const string route = 'route';

    #[Describe([Describe::required => true])]
    public Admin|Auth|ContextRoute|Web $route;

    public const string parameters = 'parameters';

    /** @var array<string, string|int> */
    #[Describe([Describe::default => []])]
    public array $parameters;

    public const string nested = 'nested';

    #[Describe([Describe::default => false])]
    public bool $nested;

    public function url(): string
    {
        return $this->route->url($this->parameters);
    }

    public function active(): bool
    {
        return $this->nested
            ? $this->route->isActive(request(), $this->parameters)
            : $this->route->isExact(request(), $this->parameters);
    }

    /** @return array<string, mixed> */
    public function svg(): array
    {
        return [
            Svg::name => $this->icon,
            Svg::classname => 'h-4 w-4 opacity-70',
        ];
    }

    /** @return array<string, mixed> */
    public function navLink(): array
    {
        return [
            NavLink::url => $this->url(),
            NavLink::label => $this->label,
            NavLink::active => $this->active(),
            NavLink::svg => $this->svg(),
        ];
    }
}
