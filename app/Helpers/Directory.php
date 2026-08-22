<?php

namespace App\Helpers;

/**
 * The directories a disk holds, named once.
 *
 * A case is the directory a file is written into and the prefix every stored path
 * carries, so what wrote a file and what reads it back cannot disagree about where
 * it lives. Renaming a case orphans everything already stored under the old name:
 * the rows still point at paths nothing serves.
 */
enum Directory: string
{
    case profile_pictures = 'profile-pictures';
    case organization_icons = 'organization-icons';
}
