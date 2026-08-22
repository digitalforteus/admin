<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use Zerotoprod\DataModel\Describe;

readonly class Topnav
{
    use DataModel;

    public const string topnav = 'topnav';
    public const string leftNav = 'leftNav';

    #[Describe([Describe::default => false])]
    public bool $leftNav;

    public const string adminNav = 'adminNav';

    #[Describe([Describe::default => false])]
    public bool $adminNav;

    public const string settingsNav = 'settingsNav';

    #[Describe([Describe::default => false])]
    public bool $settingsNav;

    public const string docsNav = 'docsNav';

    #[Describe([Describe::default => false])]
    public bool $docsNav;

    public const string organizationNav = 'organizationNav';

    #[Describe([Describe::default => false])]
    public bool $organizationNav;

    public function nav(): bool
    {
        return $this->leftNav || $this->adminNav || $this->settingsNav || $this->docsNav || $this->organizationNav;
    }

    /** @return list<NavItem> */
    public function items(): array
    {
        return match (true) {
            $this->adminNav => AdminNav::items(),
            $this->settingsNav => SettingsNav::items(),
            $this->docsNav => DocsNav::items(),
            $this->organizationNav => OrganizationNav::items(),
            default => LeftNav::items(),
        };
    }
}
