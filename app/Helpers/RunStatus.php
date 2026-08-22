<?php

namespace App\Helpers;

/**
 * How a remote run's outcome is said, and coloured, in one place.
 *
 * The provider reports two fields for one fact — where a run got to, and how it
 * ended — and the second is absent until the first is finished, so the mapping
 * from that pair to a single badge is stated here once rather than at every place
 * that renders one. A value the provider adds later resolves to nothing rather than
 * to a wrong colour, and a caller renders what was reported instead; treating an
 * unknown value as a failure would report trouble that has not happened.
 */
enum RunStatus: string
{
    case success = 'success';
    case failure = 'failure';
    case cancelled = 'cancelled';
    case skipped = 'skipped';
    case neutral = 'neutral';
    case timed_out = 'timed_out';
    case action_required = 'action_required';
    case stale = 'stale';
    case startup_failure = 'startup_failure';
    case queued = 'queued';
    case pending = 'pending';
    case waiting = 'waiting';
    case requested = 'requested';
    case in_progress = 'in_progress';
    case completed = 'completed';

    /** The conclusion when the run has one, and where it got to when it does not. */
    public static function of(?string $conclusion, ?string $status): ?self
    {
        $reported = $conclusion ?? $status;

        return $reported === null ? null : self::tryFrom($reported);
    }

    public function badge(): string
    {
        return match ($this) {
            self::success => 'badge-success',
            self::failure, self::timed_out, self::startup_failure => 'badge-error',
            self::action_required, self::stale => 'badge-warning',
            self::in_progress, self::queued, self::pending, self::waiting, self::requested => 'badge-info',
            self::cancelled, self::skipped, self::neutral, self::completed => 'badge-ghost',
        };
    }

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }
}
