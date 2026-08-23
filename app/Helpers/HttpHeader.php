<?php

namespace App\Helpers;

/**
 * The headers this application reads and writes, spelled once.
 *
 * A value is the name exactly as it goes over the wire, because it is used raw and
 * nothing normalizes it. The pair that matters is the one the front end drives: a
 * request that announces itself as a partial swap is answered with a redirect
 * header rather than a redirect, which would otherwise be swapped into the page.
 *
 * The rate limit set is written in two spellings of the same numbers: the standard
 * names a current client reads, and the prefixed ones the framework has always sent
 * and an older client still reads. Both are emitted together, so dropping either
 * half silently blinds one generation of client rather than failing loudly.
 */
enum HttpHeader: string
{
    case HxRequest = 'HX-Request';
    case HxRedirect = 'HX-Redirect';
    case ContentType = 'Content-Type';
    case StrictTransportSecurity = 'Strict-Transport-Security';
    case Authorization = 'Authorization';
    case RateLimitPolicy = 'RateLimit-Policy';
    case RateLimitLimit = 'RateLimit-Limit';
    case RateLimitRemaining = 'RateLimit-Remaining';
    case RateLimitReset = 'RateLimit-Reset';
    case RetryAfter = 'Retry-After';
    case XRateLimitLimit = 'X-RateLimit-Limit';
    case XRateLimitRemaining = 'X-RateLimit-Remaining';
    case XRateLimitReset = 'X-RateLimit-Reset';
}
