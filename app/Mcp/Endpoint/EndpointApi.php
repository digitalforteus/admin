<?php

namespace App\Mcp\Endpoint;

use App\Helpers\HasEnumAttributes;
use App\Modules\Api\Support\AdminApiSchema;
use App\Modules\Api\Support\PublicApiSchema;
use App\Routes\Admin;
use App\Routes\ApiRoute;

enum EndpointApi: string
{
    use HasEnumAttributes;

    #[RoutePrefix(ApiRoute::prefix)]
    #[EndpointConfiguration(
        prefix: ApiRoute::prefix,
        route: ApiRoute::class,
        schemaAttribute: PublicApiSchema::class,
        routesFile: 'routes/api.php',
        authenticatedRoutesFile: 'routes/api_auth.php',
    )]
    case public = 'public';

    #[RoutePrefix(Admin::prefix)]
    #[EndpointConfiguration(
        prefix: Admin::prefix.'/api',
        route: Admin::class,
        schemaAttribute: AdminApiSchema::class,
        routesFile: 'routes/api_admin.php',
    )]
    case admin = 'admin';

    public function prefix(): string
    {
        return $this->enumAttribute(EndpointConfiguration::class)->prefix;
    }

    public function routePrefix(): string
    {
        return $this->enumAttribute(RoutePrefix::class)->prefix;
    }

    /** @return class-string */
    public function route(): string
    {
        return $this->enumAttribute(EndpointConfiguration::class)->route;
    }

    /** @return class-string */
    public function schemaAttribute(): string
    {
        return $this->enumAttribute(EndpointConfiguration::class)->schemaAttribute;
    }

    public function routesFile(bool $authenticated): string
    {
        return $this->enumAttribute(EndpointConfiguration::class)->routesFile($authenticated);
    }
}
