<?php

use App\Models\User;
use App\Modules\Connections\ConnectionProvider;
use App\Modules\Connections\ConnectionQuery;
use App\Modules\Connections\Github\GithubForm;
use App\Modules\Connections\Github\GithubQuery;
use App\Routes\OrganizationRoute;
use App\Sources\Db\App\Connections;
use App\Sources\Db\App\Organizations;
use App\View\DataModels\RunsTable;
use Illuminate\Support\Facades\Http;
use Zerotoprod\GitHubSdk\ApiResult;
use Zerotoprod\GitHubSdk\Models\ListRepoActionRunsResponse;
use Zerotoprod\GitHubSdk\Response as SdkResponse;

/** The address every call this plugin makes is answered at. */
function githubRuns(): string
{
    return GithubQuery::url.'/repos/octocat/hello-world/actions/runs*';
}

/**
 * One run as the provider reports it, with every field it may omit omitted.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function workflowRun(array $overrides = []): array
{
    return [
        'id' => 11,
        'name' => 'Build',
        'display_title' => 'Add the widget',
        'head_branch' => 'main',
        'path' => '.github/workflows/build.yml',
        'run_number' => 42,
        'run_attempt' => 1,
        'event' => 'push',
        'status' => 'completed',
        'conclusion' => 'success',
        'html_url' => 'https://github.com/octocat/hello-world/actions/runs/11',
        'created_at' => '2026-08-20T10:00:00Z',
        'run_started_at' => '2026-08-20T10:01:00Z',
        'actor' => ['login' => 'octocat'],
        'triggering_actor' => ['login' => 'hubot'],
        ...$overrides,
    ];
}

test('the run list renders what the provider reports, states a refusal and an empty repository differently, and is reachable only while the connection is', function (): void {
    $User = User::factory()->createOne();
    $Organization = memberOrganization($User, attributes: [Organizations::slug->value => 'acme']);
    $Connection = organizationConnection($Organization, attributes: [
        Connections::name->value => 'Hello World',
        Connections::slug->value => 'hello-world',
        Connections::provider->value => ConnectionProvider::github->name,
        Connections::config->value => [GithubForm::owner => 'octocat', GithubForm::repo => 'hello-world'],
        Connections::credentials->value => [GithubForm::token => 'secret-token'],
    ]);

    $parameters = [OrganizationRoute::organizationParameter => 'acme'];
    $index = OrganizationRoute::index->url($parameters);
    $page = OrganizationRoute::connection->url([
        ...$parameters,
        OrganizationRoute::connectionParameter => 'hello-world',
    ]);

    // The transport is the framework's, which is the whole reason this is fakeable.
    // The first stub registered for an address is the one that answers, so what the
    // provider says is a value this test moves rather than a stub it re-registers.
    $body = [];
    $status = 200;

    Http::fake([
        githubRuns() => static function () use (&$body, &$status) {
            return Http::response($body, $status);
        },
    ]);

    $body = [
        'total_count' => 2,
        'workflow_runs' => [
            workflowRun(),
            workflowRun([
                'id' => 12,
                'display_title' => null,
                'name' => 'Nightly',
                'conclusion' => null,
                'status' => 'in_progress',
                'run_attempt' => 3,
                'triggering_actor' => null,
                'run_started_at' => null,
            ]),
        ],
    ];

    $TestResponse = $this->actingAs($User)
        ->get($page)
        ->assertOk()
        ->assertSee('Hello World')
        ->assertSee('data-run-row', false)
        ->assertSee('Add the widget')
        ->assertSee('Success')
        // A run still going has no conclusion, so where it got to is what shows.
        ->assertSee('In progress')
        // A missing title falls back to the workflow's name rather than a blank cell.
        ->assertSee('Nightly')
        ->assertSee('.github/workflows/build.yml')
        ->assertSee('main')
        ->assertSee('push')
        ->assertSee('42 (attempt 3)')
        ->assertSee('hubot')
        ->assertSee('octocat')
        ->assertSee('Showing 2 of 2')
        ->assertDontSee('data-runs-empty', false)
        ->assertDontSee('data-runs-error', false)
        // The connection segment of the trail is present, and the rail carries the
        // page this plugin contributes.
        ->assertSee('data-breadcrumb-switcher', false)
        ->assertSee('Workflow Runs')
        // A secret reaches a header and nothing else.
        ->assertDontSee('secret-token');

    expect(substr_count((string) $TestResponse->getContent(), 'data-run-row'))->toBe(2);

    Http::assertSent(static function ($Request): bool {
        return str_starts_with($Request->url(), GithubQuery::url.'/repos/octocat/hello-world/actions/runs')
            && str_contains($Request->url(), GithubQuery::per_page.'='.RunsTable::perPage)
            && $Request->hasHeader('Authorization', 'Bearer secret-token');
    });

    // A run reporting nothing at all still renders a row rather than failing.
    $body = [
        'total_count' => 1,
        'workflow_runs' => [[
            'id' => 13,
            'name' => null,
            'display_title' => null,
            'conclusion' => null,
            'status' => null,
        ]],
    ];

    $this->actingAs($User)
        ->get($page)
        ->assertOk()
        ->assertSee('data-run-row', false)
        ->assertDontSee('data-runs-error', false);

    // A repository with no runs is not a failed call, and the two say so differently.
    $body = ['total_count' => 0, 'workflow_runs' => []];

    $this->actingAs($User)
        ->get($page)
        ->assertOk()
        ->assertSee('data-runs-empty', false)
        ->assertSee('No runs')
        ->assertDontSee('data-runs-error', false);

    // A revoked token is an expected condition, not an exception.
    $body = ['message' => 'Bad credentials'];
    $status = 401;

    $this->actingAs($User)
        ->get($page)
        ->assertOk()
        ->assertSee('data-runs-error', false)
        ->assertSee('401')
        ->assertDontSee('data-run-row', false)
        ->assertDontSee('data-runs-empty', false);

    expect(ConnectionProvider::github->plugin()->verify($Connection->refresh()))->toBeFalse();

    $body = ['total_count' => 0, 'workflow_runs' => []];
    $status = 200;

    expect(ConnectionProvider::github->plugin()->verify($Connection->refresh()))->toBeTrue();

    // Pagination is prev/next over what the provider counted.
    $body = [
        'total_count' => 45,
        'workflow_runs' => array_fill(0, RunsTable::perPage, workflowRun()),
    ];

    $this->actingAs($User)
        ->get($page)
        ->assertOk()
        ->assertSee('data-runs-next', false)
        ->assertDontSee('data-runs-previous', false);

    $this->actingAs($User)
        ->get($page.'?'.RunsTable::page.'=2')
        ->assertOk()
        ->assertSee('data-runs-previous', false)
        ->assertSee('data-runs-next', false);

    Http::assertSent(static fn ($Request): bool => str_contains($Request->url(), RunsTable::page.'=2'));

    // The page is a query parameter on the connection's own address, so a link back
    // to a page keeps the whole context rather than only the number.
    $Paged = RunsTable::from([
        RunsTable::organization => 'acme',
        RunsTable::connection => 'hello-world',
        RunsTable::total => 45,
        RunsTable::page => 2,
        RunsTable::runs => array_fill(0, RunsTable::perPage, []),
    ]);

    expect($Paged->previousUrl())->toBe($page.'?'.RunsTable::page.'=1')
        ->and($Paged->nextUrl())->toBe($page.'?'.RunsTable::page.'=3')
        ->and(RunsTable::from([
            RunsTable::organization => 'acme',
            RunsTable::connection => 'hello-world',
        ])->nextUrl())->toBeNull();

    // The package wraps only its own transport's responses, so both shapes are read
    // and anything else is a refusal rather than a crash.
    $Wrapped = new ApiResult(
        response: new SdkResponse(200, [], '{}'),
        data: ListRepoActionRunsResponse::from(['total_count' => 3, 'workflow_runs' => []]),
    );

    expect(GithubQuery::read($Wrapped))->toBe([true, 200, ['total_count' => 3, 'workflow_runs' => []]])
        ->and(GithubQuery::read(new ApiResult(response: new SdkResponse(404, [], '{}'))))->toBe([false, 404, []])
        ->and(GithubQuery::read('not a response'))->toBe([false, 0, []]);

    // Existence is not public, so a stranger is told the page is not there.
    $this->forgetCredentials()
        ->actingAs(User::factory()->createOne())
        ->get($page)
        ->assertNotFound();

    // Switching the connection off sends a caller back rather than rendering.
    $this->forgetCredentials();
    ConnectionQuery::disable($Organization, $Connection);

    $this->actingAs($User)->get($page)->assertRedirect($index);

    // Removing the provider from the registry leaves the row exactly as it was and
    // renders it unavailable. This is the test the whole design exists to pass.
    ConnectionQuery::enable($Organization, $Connection);
    $Connection->update([Connections::provider->value => 'stripe']);

    $this->actingAs($User)->get($page)->assertRedirect($index);

    $this->actingAs($User)
        ->get(OrganizationRoute::connections->url($parameters))
        ->assertOk()
        ->assertSee('Hello World')
        ->assertSee('data-connection-unavailable', false);

    expect($Connection->refresh()->credentials)->toBe([GithubForm::token => 'secret-token'])
        ->and($Connection->refresh()->config)->toEqualCanonicalizing([GithubForm::owner => 'octocat', GithubForm::repo => 'hello-world'])
        ->and(ConnectionQuery::enabledFor($Organization))->toBeEmpty();

    // Restoring the key restores the row.
    $Connection->update([Connections::provider->value => ConnectionProvider::github->name]);

    expect(collect(ConnectionQuery::enabledFor($Organization))->pluck(Connections::slug->value)->all())
        ->toBe(['hello-world']);
});
