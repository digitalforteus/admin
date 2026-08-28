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
    use HasEnumAttributes;

    #[RunStatusBadge('badge-success')]
    case success = 'success';

    #[RunStatusBadge('badge-error')]
    case failure = 'failure';

    #[RunStatusBadge('badge-ghost')]
    case cancelled = 'cancelled';

    #[RunStatusBadge('badge-ghost')]
    case skipped = 'skipped';

    #[RunStatusBadge('badge-ghost')]
    case neutral = 'neutral';

    #[RunStatusBadge('badge-error')]
    case timed_out = 'timed_out';

    #[RunStatusBadge('badge-warning')]
    case action_required = 'action_required';

    #[RunStatusBadge('badge-warning')]
    case stale = 'stale';

    #[RunStatusBadge('badge-error')]
    case startup_failure = 'startup_failure';

    #[RunStatusBadge('badge-info')]
    case queued = 'queued';

    #[RunStatusBadge('badge-info')]
    case pending = 'pending';

    #[RunStatusBadge('badge-info')]
    case waiting = 'waiting';

    #[RunStatusBadge('badge-info')]
    case requested = 'requested';

    #[RunStatusBadge('badge-info')]
    case in_progress = 'in_progress';

    #[RunStatusBadge('badge-ghost')]
    case completed = 'completed';

    /** The conclusion when the run has one, and where it got to when it does not. */
    public static function of(?string $conclusion, ?string $status): ?self
    {
        $reported = $conclusion ?? $status;

        return $reported === null ? null : self::tryFrom($reported);
    }

    public function badge(): string
    {
        return $this->enumAttribute(RunStatusBadge::class)->badge;
    }

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }
}
