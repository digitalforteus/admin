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
 *
 * A deployment storing on the machine it runs on keeps nothing a person uploads: the
 * next release, the next container, the second instance behind the balancer all
 * answer without the file. So the query here refuses uploads outside development
 * until the configured store is one that outlives the machine, and every surface
 * offering an upload asks it first rather than deciding for itself.
 */
enum Disk: string
{
    public const string link = 'storage';
    public const string ephemeral = 'local';

    case public = 'public';

    /** @return bool Whether an upload outlives the machine that took it. */
    public static function retains(): bool
    {
        if (! app()->isProduction()) {
            return true;
        }

        $configured = config('filesystems.default');
        $driver = is_string($configured) ? config('filesystems.disks.'.$configured.'.driver') : null;

        return $driver !== self::ephemeral;
    }

    /** @return string The url the file at this path is served at. */
    public function url(string $path): string
    {
        return asset(self::link.'/'.$path);
    }
}
