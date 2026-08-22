<?php

use App\Helpers\HttpVerb;
use App\Helpers\Rule;
use App\Helpers\SvgName;
use App\Models\User;
use App\Modules\Api\Api;
use App\Modules\Api\Support\AbilityQuery;
use App\Modules\Api\Support\ErrorCode;
use App\Modules\Login\LoginRequest;
use App\Modules\Settings\Credentials\TokenForm;
use App\Modules\Settings\Credentials\TokenUpdateRequest;
use App\Routes\Admin;
use App\Routes\ApiRoute;
use App\Routes\Auth;
use App\Routes\Web;
use App\Sources\Db\App\PersonalAccessTokens;
use App\View\DataModels\AbilityRow;
use App\View\DataModels\AbilityTable;
use App\View\DataModels\CredentialRow;
use App\View\DataModels\CredentialsTable;
use App\View\DataModels\TextInput;
use App\View\ViewDirectory;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Tests\Fixtures\NumericApiRoute;
use Tests\Fixtures\RequestStub;
use Zerotoprod\DataModel\PropertyRequiredException;

/** @param  array<string, mixed>  $overrides */
function abilityRow(array $overrides = []): AbilityRow
{
    return AbilityRow::from([
        AbilityRow::path => ApiRoute::user->value,
        AbilityRow::verbs => [HttpVerb::get, HttpVerb::patch],
        ...$overrides,
    ]);
}

/** @param  array<string, mixed>  $overrides */
function abilityTable(array $overrides = []): AbilityTable
{
    return AbilityTable::from([
        AbilityTable::id => '01JZZZZZZZZZZZZZZZZZZZZZZZ',
        AbilityTable::name => 'Laptop CLI',
        ...$overrides,
    ]);
}

/** @return array<string, mixed> */
function credentialToken(User $User, ?string $expiresAt = null): array
{
    return issuedToken(
        $User,
        $User->createToken('Laptop CLI', expiresAt: $expiresAt === null ? null : Carbon::parse($expiresAt)),
    )->toArray();
}

/** @param  array<string, mixed>  $overrides */
function credentialsTable(array $overrides = []): CredentialsTable
{
    return CredentialsTable::from([
        CredentialsTable::tokens => [],
        ...$overrides,
    ]);
}

test('the envelope, the abilities a token may hold and the rows that render them are all read off the routes themselves', function (): void {
    $NotFound = api_response()->notFound(ErrorCode::unauthorized, ['id' => 1]);

    expect($NotFound->getStatusCode())->toBe(404)
        ->and($NotFound->getData(true))->toBe([
            'success' => false,
            'message' => ErrorCode::unauthorized->value,
            'errors' => [ErrorCode::unauthorized->value],
            'data' => ['id' => 1],
            'type' => 'error',
        ]);

    $Conflict = api_response()->conflict(ErrorCode::missing_ability);

    expect($Conflict->getStatusCode())->toBe(409)
        ->and($Conflict->getData(true))->toBe([
            'success' => false,
            'message' => ErrorCode::missing_ability->value,
            'errors' => [ErrorCode::missing_ability->value],
            'type' => 'error',
        ]);

    $Unsupported = api_response()->unsupportedMediaType(ErrorCode::unsupported_media_type);

    expect($Unsupported->getStatusCode())->toBe(415)
        ->and($Unsupported->getData(true))->toBe([
            'success' => false,
            'message' => ErrorCode::unsupported_media_type->value,
            'errors' => [ErrorCode::unsupported_media_type->value],
            'type' => 'error',
        ]);

    $Unprocessable = api_response()->unprocessableEntity(Validator::make([], ['name' => 'required']));

    expect($Unprocessable->getStatusCode())->toBe(422)
        ->and($Unprocessable->getData(true))->toBe([
            'success' => false,
            'message' => 'unprocessable entity',
            'errors' => ['name' => ['The name field is required.']],
            'type' => 'error',
        ]);

    $Created = api_response()->created(['id' => 1]);

    expect($Created->getStatusCode())->toBe(201)
        ->and($Created->getData(true))->toBe([
            'success' => true,
            'data' => ['id' => 1],
        ])
        ->and(api_response()->ok(['id' => 1])->getData(true))->toBe([
            'success' => true,
            'data' => ['id' => 1],
        ]);

    $data = [
        'id' => 1,
        'secret' => 'hidden',
        'items' => [['name' => 'one', 'secret' => 'hidden'], ['name' => 'two', 'secret' => 'hidden']],
        'profile' => (object) ['city' => 'Fort Wayne', 'secret' => 'hidden'],
    ];

    expect(api_response()->created($data, [
        'id',
        'absent',
        'items' => ['name'],
        'profile' => ['city'],
        'absent_group' => ['city'],
    ])->getData(true))->toBe([
        'success' => true,
        'data' => [
            'id' => 1,
            'items' => [['name' => 'one'], ['name' => 'two']],
            'profile' => ['city' => 'Fort Wayne'],
        ],
    ])
        ->and(api_response()->ok(RequestStub::make(), [RequestStub::website])->getData(true))->toBe([
            'success' => true,
            'message' => 'RequestStub',
            'data' => ['website' => 'https://example.com'],
            'type' => 'RequestStub',
        ])
        ->and(api_response())->toBeInstanceOf(Api::class)
        ->and(render_url(Auth::verificationVerify->value, ['id' => 1, 'hash' => 'abc']))->toBe('/email/verify/1/abc')
        ->and(render_url(Web::login->value, []))->toBe(Web::login->value);

    $RequestStub = RequestStub::make();

    expect($RequestStub->rules())->toBe([
        RequestStub::website => [Rule::required->value, Rule::url->value],
        RequestStub::secret => [Rule::nullable->value, Rule::string->value],
        RequestStub::callable => [Rule::nullable->value, Rule::max(10)],
    ])
        ->and($RequestStub->messages())->toBe([
            RequestStub::website.'.'.Rule::required->value => 'A website is required.',
        ])
        ->and($RequestStub->attributes())->toBe([RequestStub::website => 'website address'])
        ->and($RequestStub->validator())->toBe([
            $RequestStub->toArray(),
            $RequestStub->rules(),
            $RequestStub->messages(),
            $RequestStub->attributes(),
        ])
        // A column definition backs the rules, and a request appends to them.
        ->and(LoginRequest::from([LoginRequest::email => 'john@example.com', LoginRequest::password => 'password'])->rules())
        ->toBe([
            LoginRequest::email => [Rule::required->value, Rule::string->value, Rule::max(255), Rule::email->value],
            LoginRequest::password => [Rule::required->value, Rule::string->value, Rule::max(255)],
        ]);

    $User = User::factory()->createOne();

    $Request = Request::create('/');
    $Request->setUserResolver(fn (): User => $User);

    expect(User::authenticated($Request))->toBe($User)
        ->and(static fn () => User::authenticated(Request::create('/')))
        ->toThrow(AuthenticationException::class);

    $User = User::factory()->createOne();
    $this->actingAs($User);
    $Other = User::factory()->createOne();

    expect((new User)->resolveRouteBinding($User->id))->toBe($User)
        ->and((new User)->resolveRouteBinding($Other->id)?->is($Other))->toBeTrue()
        ->and(HttpVerb::of(Request::create(ApiRoute::user->value, 'HEAD')))->toBe(HttpVerb::get)
        ->and(HttpVerb::of(Request::create(ApiRoute::user->value, 'PATCH')))->toBe(HttpVerb::patch)
        ->and(HttpVerb::delete->ability(ApiRoute::user->value))->toBe('DELETE'.HttpVerb::separator.ApiRoute::user->value)
        ->toBe(HttpVerb::delete->ability(ltrim(ApiRoute::user->value, '/')));

    foreach (array_keys(AbilityQuery::get()) as $path) {
        expect(ApiRoute::tryFrom($path))->not->toBeNull();
    }

    expect(array_keys(AbilityQuery::get()))
        ->not->toContain(ApiRoute::readme->value, ApiRoute::authenticated->value)
        ->and(AbilityQuery::get()[ApiRoute::user->value])->toBe([HttpVerb::get]);

    $expected = [];

    foreach (AbilityQuery::get() as $path => $verbs) {
        foreach ($verbs as $Verb) {
            $expected[] = $Verb->ability($path);
        }
    }

    $abilities = AbilityQuery::abilities();
    $get = AbilityQuery::getAbilities();

    expect($abilities)->toBe($expected)
        ->and($abilities)->toContain(HttpVerb::get->ability(ApiRoute::user->value))
        ->and($get)->not->toBeEmpty()
        ->and(array_filter($get, static fn (string $ability): bool => str_starts_with($ability, HttpVerb::get->value.HttpVerb::separator)))->toBe($get)
        ->and(array_keys(AbilityQuery::groups()))->toBe(['public']);

    $this->actingAs(adminUser());

    expect(array_keys(AbilityQuery::groups()))->toBe(['public', 'admin'])
        ->and(AbilityQuery::groups()['public'])->toHaveKey(ApiRoute::user->value)
        ->and(AbilityQuery::groups()['admin'])->toHaveKey(Admin::api_users->value)
        ->and(AbilityQuery::abilities())->toContain(HttpVerb::get->ability(Admin::api_users->value));

    $schemas = Config::array('openapi.schemas');

    Config::set('openapi.schemas', [
        'invalid' => ['route_index' => 'NotAnEnum'],
        'numeric' => ['route_index' => NumericApiRoute::class],
    ]);

    expect(AbilityQuery::groups())->toBe([
        'numeric' => [],
    ]);

    Config::set('openapi.schemas', $schemas);

    $AbilityRow = abilityRow();

    expect(static fn () => AbilityRow::from([AbilityRow::path => ApiRoute::user->value]))
        ->toThrow(PropertyRequiredException::class)
        ->and($AbilityRow->granted)->toBeEmpty()
        ->and($AbilityRow->every)->toBeFalse()
        ->and($AbilityRow->ability(HttpVerb::get))->toBe('GET'.HttpVerb::separator.ApiRoute::user->value)
        ->and($AbilityRow->bound(HttpVerb::get))->toBeTrue()
        ->and($AbilityRow->bound(HttpVerb::patch))->toBeTrue()
        ->and($AbilityRow->bound(HttpVerb::delete))->toBeFalse();

    $Granted = abilityRow([
        AbilityRow::granted => [HttpVerb::get->ability(ApiRoute::user->value)],
    ]);
    $Every = abilityRow([AbilityRow::every => true]);
    $Elsewhere = abilityRow([
        AbilityRow::granted => [HttpVerb::get->ability(ApiRoute::authenticated->value)],
    ]);

    expect($Granted->checked(HttpVerb::get))->toBeTrue()
        ->and($Granted->checked(HttpVerb::patch))->toBeFalse()
        ->and($Every->checked(HttpVerb::get))->toBeTrue()
        ->and($Every->checked(HttpVerb::delete))->toBeTrue()
        ->and($Elsewhere->checked(HttpVerb::get))->toBeFalse()
        ->and(static fn () => AbilityTable::from([AbilityTable::name => 'Laptop CLI']))->toThrow(PropertyRequiredException::class)
        ->and(abilityTable()->granted)->toBeEmpty()
        ->and(abilityTable()->every())->toBeFalse()
        ->and(abilityTable([AbilityTable::granted => [HttpVerb::every]])->every())->toBeTrue()
        ->and(abilityTable()->verbs())->toBe(HttpVerb::cases());

    $paths = array_map(static fn (AbilityRow $Row): string => $Row->path, abilityTable()->rows());

    expect($paths)->toBe(array_keys(AbilityQuery::get()))
        ->and($paths)->toContain(ApiRoute::user->value)
        // A path reached without a token is never offered.
        ->and($paths)->not->toContain(ApiRoute::readme->value)
        ->and($paths)->not->toContain(ApiRoute::authenticated->value);

    $granted = [HttpVerb::get->ability(ApiRoute::user->value)];

    foreach (abilityTable([AbilityTable::granted => $granted])->rows() as $Row) {
        expect($Row->granted)->toBe($granted)
            ->and($Row->every)->toBeFalse();
    }

    foreach (abilityTable([AbilityTable::granted => [HttpVerb::every]])->rows() as $Row) {
        expect($Row->every)->toBeTrue();
    }

    $this->actingAs(adminUser());
    $groups = abilityTable()->groups();

    expect(array_keys($groups))->toBe(['public', 'admin'])
        ->and($groups['public'])->not->toBeEmpty()
        ->and($groups['admin'])->not->toBeEmpty()
        ->and(abilityTable()->action())->toBe(Auth::settingsCredential->url([Auth::credentialParameter => abilityTable()->id]))
        ->and(abilityTable()->mcpConnection('public'))->toBe([
            'base_url' => url('/'),
            'openapi_url' => url('openapi.json'),
            'headers' => 'Authorization:Bearer <token>',
            'llms_url' => url(Web::llms->value),
        ])
        ->and(AbilityTable::field)->toBe(TokenUpdateRequest::abilities.'[]');

    $ability = HttpVerb::get->ability(ApiRoute::user->value);

    expect(TokenUpdateRequest::from()->abilities)->toBeEmpty()
        ->and(TokenUpdateRequest::from([TokenUpdateRequest::abilities => []])->abilities)->toBeEmpty()
        ->and(TokenUpdateRequest::from([TokenUpdateRequest::abilities => [$ability]])->abilities)->toBe([$ability])
        // A wildcard, a path no endpoint is gated by, and a verb the path does
        // not answer are each dropped.
        ->and(TokenUpdateRequest::from([
            TokenUpdateRequest::abilities => [$ability, HttpVerb::every, 'PUT'.HttpVerb::separator.'/api/nowhere'],
        ])->abilities)->toBe([$ability])
        ->and(TokenUpdateRequest::from([
            TokenUpdateRequest::abilities => [HttpVerb::put->ability(ApiRoute::user->value)],
        ])->abilities)->toBeEmpty()
        ->and(TokenUpdateRequest::from([TokenUpdateRequest::abilities => 'GET:/api/user'])->abilities)->toBeEmpty()
        ->and(TokenUpdateRequest::from([TokenUpdateRequest::abilities => [['nested']]])->abilities)->toBeEmpty();

    $User = User::factory()->createOne();
    $CredentialRow = CredentialRow::from(credentialToken($User));

    expect($CredentialRow->name)->toBe('Laptop CLI')
        ->and($CredentialRow->id)->not->toBeEmpty()
        ->and(array_keys(get_object_vars($CredentialRow)))->not->toContain(PersonalAccessTokens::token->value)
        ->and(static fn () => CredentialRow::from([CredentialRow::name => 'Laptop CLI']))
        ->toThrow(PropertyRequiredException::class);

    $CredentialRow = CredentialRow::from(credentialToken(User::factory()->createOne()));
    $cells = $CredentialRow->cells();

    expect($CredentialRow->cell(PersonalAccessTokens::last_used_at))->toBe('—')
        ->and($CredentialRow->cell(PersonalAccessTokens::expires_at))->toBe('—')
        ->and($CredentialRow->cell(PersonalAccessTokens::created_at))->toBe(now()->toFormattedDateString())
        ->and(CredentialsTable::columns())->not->toContain(PersonalAccessTokens::abilities)
        ->and($cells)->not->toContain('*')
        ->and($cells)->toHaveSameSize(CredentialsTable::columns())
        ->and($cells[0])->toBe('Laptop CLI')
        ->and($CredentialRow->url())
        ->toBe(Auth::settingsCredential->url([Auth::credentialParameter => $CredentialRow->id]));

    $User = User::factory()->createOne();

    expect(CredentialRow::from(credentialToken($User, now()->addDay()->toDateTimeString()))->expired())->toBeFalse()
        ->and(CredentialRow::from(credentialToken($User, now()->subDay()->toDateTimeString()))->expired())->toBeTrue()
        ->and(CredentialRow::from(credentialToken($User))->expired())->toBeFalse()
        ->and(static fn () => CredentialsTable::from())->toThrow(PropertyRequiredException::class);

    $properties = array_keys(get_class_vars(CredentialRow::class));

    foreach (CredentialsTable::columns() as $Column) {
        expect(PersonalAccessTokens::tryFrom($Column->value))->toBe($Column)
            ->and($properties)->toContain($Column->value);
    }

    expect(CredentialsTable::columns())->not->toContain(PersonalAccessTokens::token);

    $headers = credentialsTable()->headers();

    expect($headers)->toHaveSameSize(CredentialsTable::columns())
        ->and($headers['Name'])->toBe(PersonalAccessTokens::name->comment())
        // A timestamp is headed without the suffix its column name carries.
        ->and(array_keys($headers))->toContain('Last Used', 'Expires', 'Created')
        ->and($headers['Expires'])->toBe(PersonalAccessTokens::expires_at->comment())
        // The empty row spans the headings and the revoke column.
        ->and(credentialsTable()->span())->toBe(count(CredentialsTable::columns()) + 1)
        ->and(credentialsTable()->action())->toBe(Auth::settingsCredentials->value)
        ->and(credentialsTable()->nameInput()[TextInput::name])->toBe(TokenForm::name)
        ->and(credentialsTable()->expiresAtInput()[TextInput::name])->toBe(TokenForm::expires_at)
        ->and(credentialsTable()->expiresAtInput()[TextInput::value])->toBe(now()->addDays(CredentialsTable::expiryDays)->toDateString())
        ->and(TextInput::from(credentialsTable()->nameInput())->icon)->toBe(SvgName::command_line)
        ->and(ViewDirectory::svg->has(SvgName::command_line))->toBeTrue();

    $Store = new Store('test', new ArraySessionHandler(1));
    $Store->put('_old_input', [TokenForm::expires_at => '2030-01-01']);
    request()->setLaravelSession($Store);

    expect(credentialsTable()->expiresAtInput()[TextInput::value])->toBe('2030-01-01')
        ->and(credentialsTable()->issued)->toBeNull();

    session()->put(CredentialsTable::sessionKey, 'plain-text-token');

    expect(credentialsTable()->issued)->toBe('plain-text-token');

    $User = User::factory()->createOne();
    $tokens = [
        issuedToken($User, $User->createToken('newer'))->toArray(),
        issuedToken($User, $User->createToken('older'))->toArray(),
    ];

    $rows = credentialsTable([CredentialsTable::tokens => $tokens])->rows();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->name)->toBe('newer')
        ->and($rows[1]->name)->toBe('older');
});
