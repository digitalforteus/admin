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

    public const string nav = 'nav';

    #[Describe([Describe::default => [Nav::class, 'active']])]
    public ?Nav $nav;

    public const string theme = 'theme';

    #[Describe([Describe::default => [self::class, 'userTheme']])]
    public ?string $theme;

    /** @return array<string, mixed> */
    public function topnav(): array
    {
        return [Topnav::nav => $this->nav];
    }

    public static function userTheme(): ?string
    {
        $User = Auth::user();

        return ($User instanceof User ? $User->theme : Theme::auto)->attribute();
    }
}
