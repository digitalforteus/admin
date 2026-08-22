<?php

namespace App\Helpers;

/**
 * The standing a member holds inside one organization.
 *
 * This is per-organization and is stored on the membership row, so it is not the
 * application-wide role the sibling vocabulary names and nothing here grants
 * anything outside the organization the row belongs to. Every authorization
 * question about an organization is answered from that row and from nothing else:
 * a column recording who created something is history, not standing. The order the
 * cases are declared in is the order of authority, so a case added in the middle
 * silently re-ranks every case beneath it, and an organization is required to keep
 * at least one member at the top of it.
 */
enum OrganizationRole: string
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
