<?php

namespace App\Http\Middleware;

use App\Helpers\Depth;
use App\Models\User;
use App\Modules\Contexts\Context;
use App\Modules\Contexts\DepthQuery;
use App\Routes\ContextRoute;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class ResolveContext
{
    public function handle(Request $Request, Closure $Closure): Response
    {
        $User = User::authenticated($Request);

        foreach (Depth::chain() as $Depth) {
            $slug = self::named($Request, $Depth);

            if ($slug === null) {
                break;
            }

            $Parent = $Depth->parent() instanceof Depth ? Context::of($Depth->parent()) : null;
            $Model = DepthQuery::resolve($Depth, $Parent, $slug, $User);

            if (! $Model instanceof Model) {
                $Above = $Depth->parent();

                if (! $Depth->redirectsWhenAbsent() || ! $Above instanceof Depth) {
                    abort(404);
                }

                if (! self::addressed($Request, $slug)) {
                    break;
                }

                return redirect(ContextRoute::of($Above)->url(ContextRoute::parameters()));
            }

            Context::bind($Request, $Depth, $Model);
        }

        return $Closure($Request);
    }

    /** Whether the path stops at this depth rather than naming it on the way somewhere. */
    private static function addressed(Request $Request, string $slug): bool
    {
        $segments = $Request->segments();

        return end($segments) === $slug;
    }

    private static function named(Request $Request, Depth $Depth): ?string
    {
        $parameter = $Request->route($Depth->value);

        if ($parameter instanceof Model) {
            $slug = $parameter->getAttribute('slug');

            return is_string($slug) ? $slug : null;
        }

        return is_string($parameter) && $parameter !== '' ? $parameter : null;
    }
}
