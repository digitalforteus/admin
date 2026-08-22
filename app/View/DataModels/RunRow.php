<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Helpers\RunStatus;
use Illuminate\Support\Carbon;
use Zerotoprod\DataModel\Describe;

#[Describe([Describe::nullable => true])]
readonly class RunRow
{
    use DataModel;

    public const string status = 'status';

    public ?string $status;

    public const string conclusion = 'conclusion';

    public ?string $conclusion;

    public const string title = 'title';

    public ?string $title;

    public const string html_url = 'html_url';

    public ?string $html_url;

    public const string workflow = 'workflow';

    public ?string $workflow;

    public const string branch = 'branch';

    public ?string $branch;

    public const string event = 'event';

    public ?string $event;

    public const string number = 'number';

    public ?int $number;

    public const string attempt = 'attempt';

    public ?int $attempt;

    public const string actor = 'actor';

    public ?string $actor;

    public const string started = 'started';

    public ?string $started;

    public function state(): ?RunStatus
    {
        return RunStatus::of($this->conclusion, $this->status);
    }

    public function badge(): string
    {
        return $this->state()?->badge() ?? 'badge-ghost';
    }

    public function stateLabel(): string
    {
        return $this->state()?->label() ?? $this->conclusion ?? $this->status ?? '—';
    }

    public function run(): string
    {
        return $this->title ?? '—';
    }

    public function attemptLabel(): string
    {
        if ($this->number === null) {
            return '—';
        }

        return $this->attempt !== null && $this->attempt > 1
            ? $this->number.' (attempt '.$this->attempt.')'
            : (string) $this->number;
    }

    public function startedAt(): string
    {
        return $this->started !== null ? Carbon::parse($this->started)->diffForHumans() : '—';
    }
}
