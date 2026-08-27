<?php

namespace App\Helpers;

/**
 * Which way an ordered listing runs.
 *
 * The value is both what a link carries and what the database is asked to order
 * by, so it is only ever reached through a case: a direction taken straight off a
 * request reaches the query unchecked, and anything unrecognised has to collapse
 * onto a case before it gets there. A heading already ordered links to the
 * opposite, so the pair is closed — a third case would have no opposite to offer
 * and no indicator to draw, and both are answered by exhaustive matching, which
 * fails loudly until one is given.
 */
enum SortDirection: string
{
    use HasEnumAttributes;

    #[OppositeDirection(self::desc)]
    #[SortIcon(SvgName::chevron_up)]
    #[SortAria('ascending')]
    case asc = 'asc';

    #[OppositeDirection(self::asc)]
    #[SortIcon(SvgName::chevron_down)]
    #[SortAria('descending')]
    case desc = 'desc';

    public function opposite(): self
    {
        return $this->enumAttribute(OppositeDirection::class)->direction;
    }

    public function icon(): SvgName
    {
        return $this->enumAttribute(SortIcon::class)->icon;
    }

    public function aria(): string
    {
        return $this->enumAttribute(SortAria::class)->aria;
    }
}
