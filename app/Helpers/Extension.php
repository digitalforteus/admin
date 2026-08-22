<?php

namespace App\Helpers;

use ReflectionEnumBackedCase;

/**
 * The file extensions this application accepts, named once.
 *
 * A case is the extension as it is written after the dot, so validation, the picker
 * a browser opens and anything that reports what may be sent all read the same
 * vocabulary and cannot drift apart. What a case is accepted for is the tag it
 * carries rather than a list kept beside it, so a case answering to no tag is
 * accepted nowhere — which is silent until someone tries to send one.
 */
enum Extension: string
{
    #[Image]
    case jpg = 'jpg';

    #[Image]
    case jpeg = 'jpeg';

    #[Image]
    case png = 'png';

    #[Image]
    case webp = 'webp';

    /** @return list<self> The extensions an image may be sent as. */
    public static function images(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $Extension): bool => new ReflectionEnumBackedCase(self::class, $Extension->name)
                ->getAttributes(Image::class) !== [],
        ));
    }

    /** @return list<string> The values of the extensions an image may be sent as. */
    public static function imageValues(): array
    {
        return array_map(static fn (self $Extension): string => $Extension->value, self::images());
    }

    /** @return string The extensions an image may be sent as, as a file picker filters them. */
    public static function imageFilter(): string
    {
        return implode(',', array_map(static fn (self $Extension): string => '.'.$Extension->value, self::images()));
    }
}
