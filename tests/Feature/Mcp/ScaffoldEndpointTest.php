<?php

use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ScaffoldEndpoint;

/** @return array<string, mixed> */
function scaffoldArguments(): array
{
    return [
        'module' => 'Widget/Index',
        'method' => 'get',
        'path' => '/api/widgets',
        'operation_id' => 'listWidgets',
        'summary' => 'List the widgets of the authenticated user.',
        'tags' => ['Widgets'],
        'success_description' => 'The widgets.',
        'response_fields' => [
            ['name' => 'id', 'type' => 'string', 'table' => 'Users'],
            ['name' => 'label', 'type' => 'string', 'nullable' => true, 'description' => 'What the widget is called.'],
        ],
        'dry_run' => true,
    ];
}

test('it renders the six artifacts of an authenticated index without writing them', function (): void {
    $Response = AppServer::tool(ScaffoldEndpoint::class, scaffoldArguments());

    $Response->assertOk()
        ->assertHasNoErrors()
        ->assertSee('Nothing was written.')
        ->assertSee('app/Modules/Api/Widget/Index/WidgetIndexResponse.php')
        ->assertSee('app/Modules/Api/Widget/Index/WidgetIndexSchema.php')
        ->assertSee('app/Modules/Api/Widget/Index/WidgetIndexController.php')
        ->assertSee('tests/Behavior/Api/WidgetIndexTest.php')
        ->assertSee('app/Routes/ApiRoute.php')
        ->assertSee('routes/api_auth.php')
        // A nullable response field carries the class level Describe.
        ->assertSee('#[Describe([Describe::nullable => true])]')
        ->assertSee('public ?string $label;')
        // Authentication is a declared 401, not an undocumented one.
        ->assertSee('SharedSchema::middleware_error_description')
        ->assertSee("case widgets = self::prefix.'/widgets';");

    expect(file_exists(base_path('app/Modules/Api/Widget')))->toBeFalse();
});

test('a body adds the request DTO and the 422, and an endpoint without one is public', function (): void {
    AppServer::tool(ScaffoldEndpoint::class, [
        ...scaffoldArguments(),
        'module' => 'Widget/Store',
        'method' => 'post',
        'success_status' => 201,
        'request_fields' => [
            ['name' => 'label', 'type' => 'string', 'required' => true, 'description' => 'What to call it.'],
        ],
    ])->assertOk()
        ->assertSee('app/Modules/Api/Widget/Store/WidgetStoreRequest.php')
        ->assertSee('SharedSchema::api_validation_error')
        ->assertSee('a blank label is rejected')
        ->assertSee('api_response()->created(');

    AppServer::tool(ScaffoldEndpoint::class, [
        ...scaffoldArguments(),
        'authenticated' => false,
        'security' => false,
    ])->assertOk()
        ->assertSee('routes/api.php')
        ->assertDontSee('SharedSchema::middleware_error');
});

test('a templated path writes or reuses a parameter class, and a paginated index declares its parameters', function (): void {
    AppServer::tool(ScaffoldEndpoint::class, [
        ...scaffoldArguments(),
        'module' => 'Widget/Show',
        'path' => '/api/widgets/{widget}',
        'route_case' => 'widget',
        'path_parameters' => [
            ['name' => 'widget', 'description' => 'The id of the widget.'],
        ],
    ])->assertOk()
        ->assertSee('app/Modules/Api/Widget/WidgetParameter.php')
        ->assertSee("'parameters' => [WidgetParameter::schema()],")
        ->assertSee('public function __invoke(Request $Request, string $widget): JsonResponse')
        ->assertSee("ApiRoute::widget->url([WidgetParameter::name => 'example'])");

    AppServer::tool(ScaffoldEndpoint::class, [
        ...scaffoldArguments(),
        'module' => 'Widget/Show',
        'path' => '/api/widgets/{widget}',
        'route_case' => 'widget',
        'path_parameters' => [
            ['name' => 'widget', 'description' => 'The id of the widget.', 'class' => 'App\Modules\Api\Shared\WidgetParameter'],
        ],
    ])->assertOk()
        ->assertSee('use App\Modules\Api\Shared\WidgetParameter;')
        ->assertDontSee('app/Modules/Api/Widget/WidgetParameter.php');

    AppServer::tool(ScaffoldEndpoint::class, [
        ...scaffoldArguments(),
        'paginated' => true,
        'response_fields' => [
            ['name' => 'widgets', 'type' => 'array', 'items_of' => 'App\Modules\Api\Public\User\Show\UserShowResponse'],
        ],
    ])->assertOk()
        ->assertSee("'parameters' => [...PaginationParameters::schema()],")
        ->assertSee('use App\Modules\Api\Support\PaginationResponse;')
        ->assertSee('Schema::items => UserShowResponse::data(),')
        ->assertSee('return PaginationResponse::data();')
        ->assertSee('public array $pagination;');
});

test('an admin endpoint uses the admin route index schema and session authentication', function (): void {
    AppServer::tool(ScaffoldEndpoint::class, [
        ...scaffoldArguments(),
        'api' => 'admin',
        'module' => 'Admin/User/Index',
        'path' => '/admin/api/widgets',
        'security' => false,
    ])->assertOk()
        ->assertSee('app/Modules/Api/Admin/User/Index/AdminUserIndexController.php')
        ->assertSee('app/Routes/Admin.php')
        ->assertSee('routes/api_admin.php')
        ->assertSee('use App\\Modules\\Api\\Support\\AdminApiSchema;')
        ->assertSee('#[AdminApiSchema(')
        ->assertSee('Admin::api_widgets->value')
        ->assertSee('$this->actingAs($User)')
        ->assertSee('$User = adminUser();')
        ->assertDontSee('withToken(');
});

test('a templated segment with no parameter, a module already there, or a foreign path is refused', function (): void {
    AppServer::tool(ScaffoldEndpoint::class, [
        ...scaffoldArguments(),
        'path' => '/api/widgets/{widget}',
    ])->assertHasErrors()
        ->assertSee('Every templated segment needs one entry');

    AppServer::tool(ScaffoldEndpoint::class, [
        ...scaffoldArguments(),
        'module' => 'Public/User/Show',
        'class_prefix' => 'UserShow',
        'dry_run' => false,
    ])->assertHasErrors()
        ->assertSee('app/Modules/Api/Public/User/Show/UserShowResponse.php');

    AppServer::tool(ScaffoldEndpoint::class, [
        ...scaffoldArguments(),
        'api' => 'admin',
    ])->assertHasErrors()->assertSee('The admin API path must start with /admin/api/.');
});
