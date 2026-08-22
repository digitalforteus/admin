<?php

namespace App\Modules\Connections\Github;

use App\Models\Connection;
use App\View\DataModels\RunRow;
use App\View\DataModels\RunsTable;
use Illuminate\Http\Client\Response as ClientResponse;
use Zerotoprod\GitHubSdk\ApiResult;
use Zerotoprod\GitHubSdk\GitHubSdk;
use Zerotoprod\GitHubSdk\GitHubSdkConfig;
use Zerotoprod\GitHubSdk\LaravelHttpTransport;
use Zerotoprod\GitHubSdk\Models\ListRepoActionRunsResponse;
use Zerotoprod\GitHubSdk\Models\WorkflowRun;
use Zerotoprod\GitHubSdk\Options;

readonly class GithubQuery
{
    public const string url = 'https://api.github.com';
    public const string per_page = 'per_page';

    public static function runs(Connection $Connection, int $page): GithubRuns
    {
        $response = self::client($Connection)->listRepoActionRuns(
            self::setting($Connection, GithubForm::owner),
            self::setting($Connection, GithubForm::repo),
            [Options::query => [self::per_page => RunsTable::perPage, RunsTable::page => $page]],
        );

        [$ok, $status, $body] = self::read($response);

        if (! $ok) {
            return GithubRuns::from([GithubRuns::ok => false, GithubRuns::status => $status, GithubRuns::total => 0, GithubRuns::rows => []]);
        }

        $Data = ListRepoActionRunsResponse::from($body);

        return GithubRuns::from([
            GithubRuns::ok => true,
            GithubRuns::status => $status,
            GithubRuns::total => $Data->total_count ?? 0,
            GithubRuns::rows => array_values(array_map(self::row(...), $Data->workflow_runs)),
        ]);
    }

    /** @return array{bool, int, array<array-key, mixed>} */
    public static function read(mixed $response): array
    {
        if ($response instanceof ApiResult) {
            $Data = $response->data;

            return [
                $response->ok(),
                $response->status(),
                $Data instanceof ListRepoActionRunsResponse ? $Data->toArray() : [],
            ];
        }

        if ($response instanceof ClientResponse) {
            $body = $response->json();

            return [$response->successful(), $response->status(), is_array($body) ? $body : []];
        }

        return [false, 0, []];
    }

    /** @return GitHubSdk<ClientResponse> */
    private static function client(Connection $Connection): GitHubSdk
    {
        return new GitHubSdk([
            GitHubSdkConfig::url => self::url,
            GitHubSdkConfig::headers => [
                'Authorization' => 'Bearer '.self::credential($Connection, GithubForm::token),
                'Accept' => 'application/vnd.github+json',
            ],
        ], new LaravelHttpTransport);
    }

    /** @return array<string, mixed> */
    private static function row(WorkflowRun $WorkflowRun): array
    {
        return [
            RunRow::status => $WorkflowRun->status,
            RunRow::conclusion => $WorkflowRun->conclusion,
            RunRow::title => $WorkflowRun->display_title ?? $WorkflowRun->name,
            RunRow::html_url => $WorkflowRun->html_url,
            RunRow::workflow => $WorkflowRun->path,
            RunRow::branch => $WorkflowRun->head_branch,
            RunRow::event => $WorkflowRun->event,
            RunRow::number => $WorkflowRun->run_number,
            RunRow::attempt => $WorkflowRun->run_attempt,
            RunRow::actor => ($WorkflowRun->triggering_actor ?? $WorkflowRun->actor)?->login,
            RunRow::started => $WorkflowRun->run_started_at ?? $WorkflowRun->created_at,
        ];
    }

    public static function setting(Connection $Connection, string $field): string
    {
        $value = ($Connection->config ?? [])[$field] ?? null;

        return is_string($value) ? $value : '';
    }

    private static function credential(Connection $Connection, string $field): string
    {
        $value = $Connection->credentials[$field] ?? null;

        return is_string($value) ? $value : '';
    }
}
