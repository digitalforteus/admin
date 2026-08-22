<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Helpers\Disk;
use App\Helpers\Initials;
use App\Helpers\SvgName;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Organizations\OrganizationContext;
use App\Modules\Settings\Organizations\OrganizationQuery;
use App\Routes\OrganizationRoute;
use Illuminate\Support\Facades\Auth;
use Zerotoprod\DataModel\Describe;

#[Describe([Describe::nullable => true])]
readonly class OrganizationSwitcher
{
    use DataModel;

    public const string name = 'name';

    #[Describe([Describe::required => true])]
    public string $name;

    public const string slug = 'slug';

    #[Describe([Describe::required => true])]
    public string $slug;

    public const string enterprise = 'enterprise';

    #[Describe([Describe::required => true])]
    public string $enterprise;

    public const string icon = 'icon';

    public ?string $icon;

    public const string groups = 'groups';

    /** @var list<array<string, mixed>> */
    #[Describe([Describe::default => []])]
    public array $groups;

    public static function current(): ?self
    {
        $Organization = OrganizationContext::organization();
        $User = Auth::user();

        if (! $Organization instanceof Organization || ! $User instanceof User) {
            return null;
        }

        $groups = [];

        foreach (OrganizationQuery::forUser($User) as $Organizations) {
            $groups[] = [
                OrganizationSwitcherGroup::label => $Organizations[0]->enterprise->name,
                OrganizationSwitcherGroup::active => $Organization->slug,
                OrganizationSwitcherGroup::items => array_map(static fn (Organization $Each): array => [
                    NavItem::label => $Each->name,
                    NavItem::icon => SvgName::city,
                    NavItem::route => OrganizationRoute::index,
                    NavItem::parameters => [OrganizationRoute::organizationParameter => $Each->slug],
                ], $Organizations),
            ];
        }

        return self::from([
            self::name => $Organization->name,
            self::slug => $Organization->slug,
            self::enterprise => $Organization->enterprise->name,
            self::icon => $Organization->icon,
            self::groups => $groups,
        ]);
    }

    /**
     * The declaration as the component reads it back, with nothing flattened.
     *
     * The serialising projection every model carries goes through json, which turns
     * a case into its value — and the entries here carry cases whose types cannot be
     * rebuilt from one. Handing back the properties as they stand is what lets the
     * component hydrate exactly what was built.
     *
     * @return array<string, mixed>
     */
    public function props(): array
    {
        return $this->collect()->all();
    }

    /** @return list<OrganizationSwitcherGroup> */
    public function sections(): array
    {
        return array_map(
            static fn (array $group): OrganizationSwitcherGroup => OrganizationSwitcherGroup::from($group),
            $this->groups,
        );
    }

    public function iconUrl(): ?string
    {
        return $this->icon !== null && $this->icon !== '' ? Disk::public->url($this->icon) : null;
    }

    public function initials(): string
    {
        return Initials::from($this->name);
    }
}
