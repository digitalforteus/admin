<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;

class Topnav
{
    use DataModel;

    public const string topnav = 'topnav';
    public const string leftNav = 'leftNav';

    public bool $leftNav = false;

    public const string adminNav = 'adminNav';

    public bool $adminNav = false;

    public const string settingsNav = 'settingsNav';

    public bool $settingsNav = false;

    public const string docsNav = 'docsNav';

    public bool $docsNav = false;

    public function nav(): bool
    {
        return $this->leftNav || $this->adminNav || $this->settingsNav || $this->docsNav;
    }

    /** @return list<NavItem> */
    public function items(): array
    {
        return match (true) {
            $this->adminNav => AdminNav::items(),
            $this->settingsNav => SettingsNav::items(),
            $this->docsNav => DocsNav::items(),
            default => LeftNav::items(),
        };
    }
}
