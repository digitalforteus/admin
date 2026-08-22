<?php

namespace App\Modules\Organizations;

use RuntimeException;

/**
 * Refuses the change that would leave an organization with nobody above it.
 *
 * The database cannot state the rule — it is a count across rows, not a
 * constraint on one — so the only place it holds is the query layer, and it has to
 * be raised rather than returned so no caller can drop it by ignoring a result.
 * Every path that removes or demotes a member goes through the check; one that
 * writes the row itself leaves an organization nobody can administer and no page
 * can repair.
 */
class LastOwnerException extends RuntimeException {}
