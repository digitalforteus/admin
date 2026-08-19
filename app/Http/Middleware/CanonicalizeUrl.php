<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Head\Facades\Head;
use Laravel\Head\Tags\Canonical;
use Symfony\Component\HttpFoundation\Response;

readonly class CanonicalizeUrl
{
    public function handle(Request $Request, Closure $Closure): Response
    {
        $host = $Request->getHost();
        $isSecure = $Request->isSecure();
        $needsRedirect = false;
        $newHost = $host;

        if (! $isSecure && app()->isProduction()) {
            $needsRedirect = true;
        }

        if (str_starts_with($host, 'www.')) {
            $needsRedirect = true;
            $newHost = substr($host, 4);
        }

        if ($needsRedirect) {
            $scheme = app()->isProduction() ? 'https' : $Request->getScheme();
            $url = $scheme.'://'.$newHost.$Request->getRequestUri();

            return redirect($url, 301);
        }

        Head::og(url: Canonical::make()->render($Request));

        return $Closure($Request);
    }
}
