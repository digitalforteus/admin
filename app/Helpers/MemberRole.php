<?php

namespace App\Helpers;

/**
 * The standing a member holds at one depth of the hierarchy.
 *
 * A standing is stored against a subject rather than against the application, so it is
 * not the application-wide role the sibling vocabulary names and grants nothing outside
 * the subject its row names. It reaches downwards and never upwards: a standing held at
 * one depth answers for every depth contained by it unless a nearer row overrides it,
 * so the nearest row wins and a row added closer to the subject can only narrow what a
 * further one granted. A column recording who created something is history, not
 * standing. The order the cases are declared in is the order of authority, so a case
 * added in the middle silently re-ranks every case beneath it, and a subject that can
 * be reached at all is required to keep at least one member at the top of it.
 */
enum MemberRole: string
{
    case owner = 'owner';
    case admin = 'admin';
    case member = 'member';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function atLeast(self $self): bool
    {
        return $this->rank() <= $self->rank();
    }

    public function manages(): bool
    {
        return $this->atLeast(self::admin);
    }

    private function rank(): int
    {
        $rank = array_search($this, self::cases(), true);

        return is_int($rank) ? $rank : PHP_INT_MAX;
    }
}
