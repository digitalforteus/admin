<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Helpers\Role;
use App\Helpers\SvgName;
use App\Models\User;
use App\Routes\Admin;
use App\Routes\Auth;
use App\Routes\Web;
use Zerotoprod\DataModel\Describe;

readonly class UserMenu
{
    use DataModel;

    public const string name = 'name';

    #[Describe([Describe::default => ''])]
    public string $name;

    public const string email = 'email';

    #[Describe([Describe::default => ''])]
    public string $email;

    public const string picture = 'picture';

    #[Describe([Describe::nullable => true])]
    public ?string $picture;

    /** @return array<string, mixed> */
    public function avatar(): array
    {
        return [
            Avatar::name => $this->name,
            Avatar::picture => $this->picture,
        ];
    }

    /** @return list<NavItem> */
    public static function items(): array
    {
        return [
            ...(self::isAdmin()
                ? [NavItem::from([NavItem::label => 'Admin', NavItem::icon => SvgName::command_line, NavItem::route => Admin::index])]
                : []),
            NavItem::from([NavItem::label => 'Settings', NavItem::icon => SvgName::gear, NavItem::route => Auth::settingsProfile]),
            NavItem::from([NavItem::label => 'Logout', NavItem::icon => SvgName::logout, NavItem::route => Web::logout]),
        ];
    }

    private static function isAdmin(): bool
    {
        $User = auth()->guard()->user();

        return $User instanceof User && $User->hasRole(Role::admin->value);
    }
}
