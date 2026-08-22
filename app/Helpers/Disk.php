<?php

namespace App\Helpers;

/**
 * The filesystem disks this application writes to, and how a file on one is addressed.
 *
 * A case is the name the disk is configured under, so nothing repeats that name to
 * reach a file. What is stored is always a path inside the disk; the url is composed
 * here from the web root link that exposes it, against the host the request came in
 * on, so a deployment reached on a port still addresses its own files. Without that
 * link the path resolves to nothing and the image alone breaks, while every page
 * around it still renders.
 */
enum Disk: string
{
    public const string link = 'storage';

    case public = 'public';

    /** @return string The url the file at this path is served at. */
    public function url(string $path): string
    {
        return asset(self::link.'/'.$path);
    }
}
