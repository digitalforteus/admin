<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Helpers\Extension;
use App\Helpers\Initials;
use App\Helpers\ProfilePicture as Picture;
use App\Helpers\SvgName;
use App\Models\User;
use App\Routes\Auth;
use Zerotoprod\DataModel\Describe;

class ProfilePicture
{
    use DataModel;

    public const string field = 'field';

    #[Describe([Describe::required => true])]
    public string $field;

    public const string name = 'name';

    #[Describe([Describe::default => [self::class, 'authenticatedName']])]
    public string $name;

    public const string picture = 'picture';

    #[Describe([Describe::default => [self::class, 'current']])]
    public ?string $picture;

    public const string legend = 'legend';

    public string $legend = 'Profile picture';

    public const string accept = 'accept';

    #[Describe([Describe::default => [Extension::class, 'imageFilter']])]
    public string $accept;

    public const string bag = 'bag';

    public string $bag = 'default';

    /** @return array<string, mixed> */
    public function fieldset(): array
    {
        return [
            Fieldset::legend => $this->legend,
            Fieldset::name => $this->field,
            Fieldset::bag => $this->bag,
        ];
    }

    /** @return array<string, mixed> */
    public function svg(): array
    {
        return [
            Svg::name => SvgName::pencil,
            Svg::classname => 'h-4 w-4 opacity-70',
        ];
    }

    public function url(): string
    {
        return Auth::settingsProfilePicture->value;
    }

    public function initials(): string
    {
        return Initials::from($this->name);
    }

    public static function current(): ?string
    {
        return Picture::current();
    }

    public static function authenticatedName(): string
    {
        $User = auth()->guard()->user();

        return $User instanceof User ? $User->name : '';
    }
}
