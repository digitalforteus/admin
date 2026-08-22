<?php

use App\Helpers\HttpVerb;
use App\Models\User;
use App\Modules\Settings\Credentials\TokenUpdateRequest;
use App\Routes\Admin;
use App\Routes\ApiRoute;
use App\Routes\Auth;
use App\Routes\Web;
use App\View\DataModels\AbilityTable;
use Illuminate\Support\Facades\Config;

/** The management page of a token the given account owns. */
function credentialUrl(User $User, string $name = 'Ability Grid'): string
{
    return Auth::settingsCredential->url([
        Auth::credentialParameter => issuedToken($User, $User->createToken($name))->id,
    ]);
}

test('guests and non owners are refused the page and the form', function (): void {
    $Owner = User::factory()->createOne();
    $url = credentialUrl($Owner);

    $this->get($url)->assertRedirect(Web::login->value);
    $this->post($url)->assertRedirect(Web::login->value);

    $this->actingAs(User::factory()->createOne())->get($url)->assertNotFound();
    $this->actingAs(User::factory()->createOne())->post($url)->assertNotFound();
});

test('the grid lists every endpoint and verb, with a toggle wherever one is bound', function (): void {
    $User = User::factory()->createOne();

    $TestResponse = $this->actingAs($User)->get(credentialUrl($User))->assertOk();

    $TestResponse->assertSee('data-api-group="public"', false)
        ->assertDontSee('data-api-group="admin"', false)
        ->assertSee('data-mcp-connection', false)
        ->assertSee(url(Config::string('openapi.schemas.public.route.uri')))
        ->assertSee('Authorization:Bearer &lt;token&gt;', false)
        ->assertSee('<details', false)
        ->assertSee('href="'.url(Web::llms->value).'"', false)
        ->assertDontSee('npx -y @ivotoby/openapi-mcp-server')
        ->assertSee('data-endpoint-column', false)
        ->assertSee(ApiRoute::user->value)
        // A token the ui issues starts out holding everything, and says so.
        ->assertSee('data-every-ability', false)
        ->assertSee(HttpVerb::get->ability(ApiRoute::user->value))
        ->assertDontSee(HttpVerb::put->ability(ApiRoute::user->value));

    foreach (HttpVerb::cases() as $HttpVerb) {
        $TestResponse->assertSee($HttpVerb->value)
            ->assertSee('data-ability-column="'.$HttpVerb->value.'"', false)
            ->assertSee('aria-label="Toggle all '.$HttpVerb->value.' abilities"', false);
    }
});

test('the page offers admin api abilities to an administrator', function (): void {
    $User = adminUser();

    $this->actingAs($User)
        ->get(credentialUrl($User))
        ->assertOk()
        ->assertSee('data-api-group="admin"', false)
        ->assertSee(Admin::api_users->value)
        ->assertSee(HttpVerb::get->ability(Admin::api_users->value));
});

test('the verbs ticked are the abilities the token is left holding, and are ticked when read back', function (): void {
    $User = User::factory()->createOne();
    $url = credentialUrl($User);
    $granted = [HttpVerb::get->ability(ApiRoute::user->value)];

    $this->actingAs($User)
        ->from($url)
        ->post($url, [TokenUpdateRequest::abilities => $granted])
        ->assertRedirect($url)
        ->assertSessionHas('status', 'Abilities updated.');

    expect($User->tokens()->sole()->abilities)->toBe($granted);

    $this->actingAs($User)
        ->get($url)
        ->assertOk()
        ->assertDontSee('data-every-ability', false)
        ->assertSee(AbilityTable::field);
});

test('ticking nothing, or anything the grid never offered, closes the token to the whole api', function (): void {
    $User = User::factory()->createOne();
    $url = credentialUrl($User);

    $this->actingAs($User)->post($url)->assertSessionHasNoErrors();

    expect($User->tokens()->sole()->abilities)->toBe([]);

    foreach ([
        [HttpVerb::every, 'DELETE'.HttpVerb::separator.'/api/nowhere'],
        [HttpVerb::get->ability(Admin::api_users->value)],
    ] as $abilities) {
        $this->actingAs($User)->post($url, [TokenUpdateRequest::abilities => $abilities]);

        expect($User->tokens()->sole()->abilities)->toBe([]);
    }
});
