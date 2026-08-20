<?php

namespace App\Helpers;

/**
 * The static files served from the web root, and the vocabulary a template names them by.
 *
 * A file here answers on its own, without a route and without a controller, so nothing else
 * records that it exists: a case is the whole of the declaration, and the url method is the
 * only way a template writes one. A template that writes the name itself instead is a link
 * a rename cannot follow, and it keeps rendering until someone loads the page and looks.
 */
enum PublicAsset: string
{
    case apple_touch_icon = 'apple-touch-icon.png';
    case favicon_16 = 'favicon-16x16.png';
    case favicon_32 = 'favicon-32x32.png';
    case site_webmanifest = 'site.webmanifest';

    /** @return string The url the file is served at. */
    public function url(): string
    {
        return asset($this->value);
    }
}
