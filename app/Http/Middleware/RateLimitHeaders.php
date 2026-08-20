<?php

namespace App\Http\Middleware;

use App\Helpers\HttpHeader;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * The throttle guard, answering with the standard rate limit headers as well as the
 * framework's own.
 *
 * It invents no limit: the ceiling and the window are the ones the route already
 * declares, so a route that declares none is passed through untouched and a route
 * that changes its numbers changes what is advertised without a second edit. The
 * window is only knowable here, because the framework keeps it out of the headers
 * it writes — which is why this stands in for the guard rather than beside it, and
 * why binding the alias elsewhere silently drops the standard names again.
 *
 * Where several ceilings apply at once the tightest is the one described, because a
 * looser one written second would overwrite it and promise room that is not there.
 *
 * @phpstan-type Limit object{key: string, maxAttempts: int, decaySeconds: int}
 */
class RateLimitHeaders extends ThrottleRequests
{
    /** @var array<int, Limit> */
    private array $limits = [];

    /**
     * @param  Request  $request
     * @param  array<int, Limit>  $limits
     * @return Response
     */
    protected function handleRequest($request, Closure $next, array $limits)
    {
        foreach ($limits as $limit) {
            $this->limits[$limit->maxAttempts] = $limit;
        }

        return parent::handleRequest($request, $next, $limits);
    }

    /**
     * @param  int  $maxAttempts
     * @param  int  $remainingAttempts
     * @param  int|null  $retryAfter
     * @return array<string, int|string>
     */
    protected function getHeaders($maxAttempts, $remainingAttempts, $retryAfter = null, ?Response $response = null)
    {
        $headers = parent::getHeaders($maxAttempts, $remainingAttempts, $retryAfter, $response);
        $limit = $this->limits[$maxAttempts] ?? null;
        $window = $limit === null ? 0 : $limit->decaySeconds;
        $reset = $retryAfter ?? ($limit === null ? $window : $this->limiter->availableIn($limit->key));

        return $headers === [] ? $headers : $headers + [
            HttpHeader::XRateLimitReset->value => $this->availableAt($reset),
            HttpHeader::RateLimitPolicy->value => sprintf('%d;w=%d', $maxAttempts, $window),
            HttpHeader::RateLimitLimit->value => $maxAttempts,
            HttpHeader::RateLimitRemaining->value => $remainingAttempts,
            HttpHeader::RateLimitReset->value => $reset,
        ];
    }
}
