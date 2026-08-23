<?php

namespace App\Modules\Sitemap;

use App\Routes\Web;

final readonly class Sitemap
{
    public const int urlLimit = 50_000;

    /** @return array<int, list<Web>> */
    public static function pages(): array
    {
        $pages = [];

        foreach (array_chunk(Web::sitemap(), self::urlLimit) as $index => $cases) {
            $pages[$index + 1] = $cases;
        }

        return $pages;
    }

    /** @return list<Web> */
    public static function page(int $page): array
    {
        return self::pages()[$page] ?? [];
    }

    public static function location(Web $Web): string
    {
        $url = url($Web->url());

        return $Web->url() === '/' ? rtrim($url, '/').'/' : $url;
    }

    public static function lastmod(Web ...$cases): string
    {
        $times = array_filter(array_map(self::modified(...), $cases));

        return $times === [] ? '' : '<lastmod>'.date(DATE_W3C, max($times)).'</lastmod>';
    }

    private static function modified(Web $Web): ?int
    {
        $path = trim($Web->url(), '/');
        $file = resource_path('views/pages/'.($path === '' ? '' : $path.'/').'index.blade.php');

        return is_file($file) ? (filemtime($file) ?: null) : null;
    }
}
