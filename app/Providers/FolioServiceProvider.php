<?php

namespace App\Providers;

use App\Helpers\Role;
use App\Http\Middleware\ResolveContext;
use App\Routes\MiddlewareTag;
use Illuminate\Support\ServiceProvider;
use Laravel\Folio\Folio;

class FolioServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Folio::path(resource_path('views/pages'))->middleware([
            'admin' => [MiddlewareTag::auth->value, Role::admin->middleware()],
            'admin/*' => [MiddlewareTag::auth->value, Role::admin->middleware()],
            'confirm-password' => [MiddlewareTag::auth->value, MiddlewareTag::verified->value],
            'confirm-password/*' => [MiddlewareTag::auth->value, MiddlewareTag::verified->value],
            'e' => [
                MiddlewareTag::auth->value,
                MiddlewareTag::verified->value,
                ResolveContext::class,
            ],
            'e/*' => [
                MiddlewareTag::auth->value,
                MiddlewareTag::verified->value,
                ResolveContext::class,
            ],
            'email/verify/*' => [MiddlewareTag::auth->value],
            'settings' => [MiddlewareTag::auth->value, MiddlewareTag::verified->value],
            'settings/*' => [MiddlewareTag::auth->value, MiddlewareTag::verified->value],
            '*' => [
                //
            ],
        ]);
    }
}
