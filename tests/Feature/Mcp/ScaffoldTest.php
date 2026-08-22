<?php

use App\Mcp\Endpoint\EndpointWriter;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ScaffoldEndpoint;
use App\Mcp\Tools\ScaffoldOpenApi;

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

test('the scaffolder writes an endpoint module and its route case, and the operations of an openapi document, or refuses', function (): void {
    $Writer = new ReflectionClass(EndpointWriter::class)->newInstanceWithoutConstructor();
    $insert = new ReflectionMethod(EndpointWriter::class, 'insert');
    $contents = <<<'PHP'
        enum ApiRoute: string
        {
            case authenticated = '/api/authenticated';
            #[AdminLink]
            case readme = '/api/readme';
        }
        PHP;

    expect($insert->invoke($Writer, $contents, "    case random = '/api/random';", '    case '))->toContain(<<<'PHP'
            case random = '/api/random';
            #[AdminLink]
            case readme = '/api/readme';
        PHP);

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

    $schema = [
        'openapi' => '3.1.0',
        'info' => ['title' => 'Widgets', 'version' => '1.0.0'],
        'paths' => [
            '/api/widgets/{widget}' => [
                'get' => [
                    'operationId' => 'showWidget',
                    'summary' => 'Show a widget.',
                    'tags' => ['Widgets'],
                    'parameters' => [[
                        'name' => 'widget',
                        'in' => 'path',
                        'required' => true,
                        'description' => 'The widget id.',
                        'schema' => ['type' => 'string'],
                    ]],
                    'responses' => [
                        '200' => [
                            'description' => 'The widget.',
                            'content' => ['application/json' => ['schema' => [
                                'type' => 'object',
                                'properties' => ['data' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => ['type' => 'integer', 'description' => 'The widget id.'],
                                        'label' => ['type' => ['string', 'null']],
                                    ],
                                ]],
                            ]]],
                        ],
                        '404' => ['description' => 'The widget was not found.'],
                    ],
                ],
            ],
        ],
    ];

    AppServer::tool(ScaffoldOpenApi::class, [
        'openapi' => json_encode($schema, JSON_THROW_ON_ERROR),
        'dry_run' => true,
    ])->assertOk()
        ->assertHasNoErrors()
        ->assertSee('app/Modules/Api/Widget/Show/WidgetShowResponse.php')
        ->assertSee('public int $id;')
        ->assertSee('public ?string $label;')
        ->assertSee('app/Modules/Api/Widget/WidgetParameter.php')
        ->assertSee('The widget was not found.');

    // An operation id is what keeps a foreign path off a conventional module name.
    $external = <<<'YAML'
        openapi: 3.0.4
        info:
          title: Pets
          version: 1.0.0
        paths:
          /pet/findByStatus:
            get:
              operationId: findPetsByStatus
              summary: Find pets by status.
              tags: [pet]
              responses:
                '200':
                  description: Matching pets.
        YAML;

    AppServer::tool(ScaffoldOpenApi::class, ['openapi' => $external, 'dry_run' => true])
        ->assertOk()
        ->assertHasNoErrors()
        ->assertSee('app/Modules/Api/Pet/FindByStatus/PetFindByStatusController.php')
        ->assertSee("case pet_find_by_status = self::prefix.'/pet/findByStatus';");

    $schema = <<<'YAML'
        openapi: 3.0.4
        info: {title: Admin users, version: 1.0.0}
        paths:
          /gadgets:
            get:
              operationId: listUsers
              tags: [Users]
              responses:
                '200': {description: The users.}
        YAML;

    AppServer::tool(ScaffoldOpenApi::class, [
        'api' => 'admin',
        'openapi' => $schema,
        'dry_run' => true,
    ])->assertOk()
        ->assertSee("case api_gadgets = self::prefix.'/api/gadgets';")
        ->assertSee('Admin::api_gadgets->value')
        ->assertSee('#[AdminApiSchema(')
        ->assertSee('routes/api_admin.php');

    AppServer::tool(ScaffoldOpenApi::class, [
        'openapi' => '{"swagger":"2.0"}',
        'dry_run' => true,
    ])->assertHasErrors()->assertSee('Only OpenAPI 3.x schemas are supported.');
});
