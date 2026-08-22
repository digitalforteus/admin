<?php

namespace App\Helpers;

use App\Models\User;
use App\Sources\Db\App\Users;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The picture a person is shown by, and the order the sources are tried in.
 *
 * An upload wins over anything a provider supplied, so the choice made in settings
 * is what every surface renders, and what a provider sent stands in only while
 * nothing was uploaded. What is stored is a path inside the disk and never a url,
 * so the disk decides how a file is addressed and this decides nothing about it.
 * Replacing or removing a picture discards the file it replaces, because nothing
 * else ever will.
 */
final readonly class ProfilePicture
{
    public const int kilobytes = 2048;

    public static function put(User $User, ?UploadedFile $UploadedFile): void
    {
        $path = $UploadedFile?->store(Directory::profile_pictures->value, Disk::public->value);

        self::discard($User);

        $User->update([Users::picture->value => is_string($path) ? $path : null]);
    }

    public static function clear(User $User): void
    {
        self::discard($User);

        $User->update([Users::picture->value => null]);
    }

    public static function uploaded(?Authenticatable $User): ?string
    {
        $path = $User instanceof User ? $User->picture : null;

        return is_string($path) && $path !== ''
            ? Disk::public->url($path)
            : null;
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

    private static function discard(User $User): void
    {
        $path = $User->picture;

        if (is_string($path) && $path !== '') {
            Storage::disk(Disk::public->value)->delete($path);
        }
    }
}
