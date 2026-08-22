<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Helpers\Theme;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Zerotoprod\DataModel\Describe;

readonly class Main
{
    use DataModel;

    public const string main = 'main';
    public const string classnames = 'classnames';

    #[Describe([Describe::nullable => true])]
    public ?string $classnames;

    public const string adminNav = 'adminNav';

    #[Describe([Describe::default => [AdminNav::class, 'visible']])]
    public bool $adminNav;

    public const string settingsNav = 'settingsNav';

    #[Describe([Describe::default => [SettingsNav::class, 'visible']])]
    public bool $settingsNav;

    public const string docsNav = 'docsNav';

    #[Describe([Describe::default => [DocsNav::class, 'visible']])]
    public bool $docsNav;

    public const string organizationNav = 'organizationNav';

    #[Describe([Describe::default => [OrganizationNav::class, 'visible']])]
    public bool $organizationNav;

    public const string leftNav = 'leftNav';

    #[Describe([Describe::default => [LeftNav::class, 'visible']])]
    public bool $leftNav;

    public const string theme = 'theme';

    #[Describe([Describe::default => [self::class, 'userTheme']])]
    public ?string $theme;

    public function nav(): bool
    {
        return $this->adminNav || $this->settingsNav || $this->docsNav || $this->organizationNav;
    }

    /** @return array<string, mixed> */
    public function topnav(): array
    {
        return [
            Topnav::leftNav => $this->leftNav,
            Topnav::adminNav => $this->adminNav,
            Topnav::settingsNav => $this->settingsNav,
            Topnav::docsNav => $this->docsNav,
            Topnav::organizationNav => $this->organizationNav,
        ];
    }

    public static function userTheme(): ?string
    {
        $User = Auth::user();

        return ($User instanceof User ? $User->theme : Theme::auto)->attribute();
    }
}
