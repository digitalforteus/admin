<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Routes\OrganizationRoute;
use Zerotoprod\DataModel\Describe;

readonly class OrganizationSwitcherGroup
{
    use DataModel;

    public const string label = 'label';

    #[Describe([Describe::required => true])]
    public string $label;

    public const string active = 'active';

    #[Describe([Describe::required => true])]
    public string $active;

    public const string items = 'items';

    /** @var list<array<string, mixed>> */
    #[Describe([Describe::default => []])]
    public array $items;

    /** @return list<NavItem> */
    public function items(): array
    {
        return array_map(static fn (array $item): NavItem => NavItem::from($item), $this->items);
    }

    /**
     * Whether an entry is the one the address currently names.
     *
     * The comparison is on the segment rather than on whether the path is being
     * visited, because one segment is a prefix of another the moment two names
     * share an opening — which marks the wrong entry, silently, and only for the
     * pairs that happen to collide.
     */
    public function isActive(NavItem $NavItem): bool
    {
        return ($NavItem->parameters[OrganizationRoute::organizationParameter] ?? null) === $this->active;
    }
}
