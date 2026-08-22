<?php

namespace App\Helpers;

use App\Models\User;
use App\Sources\Db\App\Users;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Which picture a person is shown by, and the order the sources are tried in.
 *
 * An upload wins over anything a provider supplied, so the choice made in settings
 * is what every surface renders, and what a provider sent stands in only while
 * nothing was uploaded. Where that upload lives is not decided here: the column and
 * the directory named once below are the whole of the association, and the generic
 * store does the rest. A surface reading a source directly instead renders a person
 * differently from every other surface, and nothing fails while it does.
 */
final readonly class ProfilePicture
{
    public static function of(User $User): Picture
    {
        return Picture::of($User, Users::picture, Directory::profile_pictures);
    }

    public static function uploaded(?Authenticatable $User): ?string
    {
        return $User instanceof User ? self::of($User)->url() : null;
    }

    public static function current(): ?string
    {
        $session = session(SessionKey::user_picture->value);

        return self::uploaded(auth()->guard()->user())
            ?? (is_string($session) && $session !== '' ? $session : null);
    }

    public static function url(?Authenticatable $User): ?string
    {
        $picture = self::uploaded($User) ?? ($User instanceof User ? $User->oauthProviders->first()?->picture : null);

        return is_string($picture) && $picture !== '' ? $picture : null;
    }
}
