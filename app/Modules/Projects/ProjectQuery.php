<?php

namespace App\Modules\Projects;

use App\Models\Organization;
use App\Models\Project;
use App\Sources\Db\App\Projects;

/**
 * What one organization holds, and nothing any other does.
 *
 * Every lookup here is scoped to the organization the address named, because the url
 * segment a project is addressed by is only unique inside one: resolving it without
 * that scope answers with whichever row was written first, silently serving one
 * organization's project under another's address. Reaching a project is holding a
 * membership in the organization above it and nothing further is stored, so an
 * account that lost the membership loses the project with it.
 */
readonly class ProjectQuery
{
    public static function bySlug(Organization $Organization, string $slug): ?Project
    {
        $Project = Project::query()
            ->where(Projects::organization_id->value, $Organization->id)
            ->where(Projects::slug->value, $slug)
            ->first();

        return $Project instanceof Project ? $Project : null;
    }

    public static function find(Organization $Organization, string $slug): Project
    {
        $Project = self::bySlug($Organization, $slug);

        if (! $Project instanceof Project) {
            abort(404);
        }

        return $Project;
    }

    /** @return list<Project> */
    public static function forOrganization(Organization $Organization): array
    {
        $Builder = Project::query()->where(Projects::organization_id->value, $Organization->id);

        $Builder->orderBy(Projects::name->value);

        return array_values($Builder->get()->all());
    }
}
