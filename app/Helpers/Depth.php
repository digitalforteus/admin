<?php

namespace App\Helpers;

use App\Models\Connection;
use App\Models\Enterprise;
use App\Models\Organization;
use App\Models\Project;
use App\Modules\Enterprises\EnterpriseForm;
use App\Modules\Organizations\Organizations\OrganizationForm;
use App\Modules\Projects\ProjectForm;
use App\Routes\ContextRoute;
use Illuminate\Database\Eloquent\Model;

/**
 * The depths of containment this application serves, one case per depth, outermost first.
 *
 * A case is the whole of declaring a depth: the case value is the route parameter it is
 * addressed by and the subject a membership names, the declaration order is the
 * containment order, and everything that walks the hierarchy reads that order rather
 * than restating it. Adding a depth in the middle re-parents the one beneath it, which
 * is the intent — and reordering cases silently re-parents them too, so the order is
 * the design and not a formatting choice. A depth holding no members is a leaf of the
 * standing a reader carries, never of the addresses: what stands beneath it is reached
 * with the standing held above it.
 */
enum Depth: string
{
    use HasEnumAttributes;

    #[DepthModel(Enterprise::class)]
    #[DepthIcon(SvgName::city)]
    #[DepthBreadcrumbForm(EnterpriseForm::class)]
    #[DepthNav(overview: true)]
    #[DepthAncestry]
    case enterprise = 'enterprise';

    #[DepthModel(Organization::class)]
    #[DepthIcon(SvgName::building)]
    #[DepthBreadcrumbForm(OrganizationForm::class)]
    #[DepthNav(overview: true, extra: new DepthNavExtra('Members', SvgName::user, ContextRoute::members))]
    #[DepthAncestry(['enterprise' => 'enterprise_id'])]
    case organization = 'organization';

    #[DepthModel(Project::class)]
    #[DepthIcon(SvgName::folder)]
    #[DepthBreadcrumbForm(ProjectForm::class)]
    #[DepthNav(overview: true, extra: new DepthNavExtra('Connections', SvgName::link, ContextRoute::connectionIndex))]
    #[DepthAncestry(['organization' => 'organization_id', 'enterprise' => 'organization.enterprise_id'])]
    case project = 'project';

    #[DepthModel(Connection::class)]
    #[DepthIcon(SvgName::link)]
    #[DepthBreadcrumbForm]
    #[DepthNav(overview: false, extra: new DepthNavExtra('Connections', SvgName::link, ContextRoute::connectionIndex), trailingIsPlugin: true)]
    #[DepthAncestry]
    case connection = 'connection';

    /** @return list<self> */
    public static function chain(): array
    {
        return self::cases();
    }

    public function parent(): ?self
    {
        $chain = self::chain();
        $index = (int) array_search($this, $chain, true);

        return $index === 0 ? null : $chain[$index - 1];
    }

    public function child(): ?self
    {
        $chain = self::chain();
        $index = (int) array_search($this, $chain, true);

        return $chain[$index + 1] ?? null;
    }

    /** @return list<self> This depth, then every depth containing it. */
    public function ancestry(): array
    {
        $ancestry = [$this];
        $Parent = $this->parent();

        while ($Parent instanceof self) {
            $ancestry[] = $Parent;
            $Parent = $Parent->parent();
        }

        return $ancestry;
    }

    public static function of(Model $Model): ?self
    {
        foreach (self::cases() as $Depth) {
            if ($Model instanceof ($Depth->model())) {
                return $Depth;
            }
        }

        return null;
    }

    /** @return class-string<Model> */
    public function model(): string
    {
        return $this->enumAttribute(DepthModel::class)->model;
    }

    public function icon(): SvgName
    {
        return $this->enumAttribute(DepthIcon::class)->icon;
    }

    /** @return class-string<HasTextInputField>|null The form a new subject of this depth is named on, or null where it cannot be created inside the trail. */
    public function breadcrumbForm(): ?string
    {
        return $this->enumAttribute(DepthBreadcrumbForm::class)->form;
    }

    public function nav(): DepthNav
    {
        return $this->enumAttribute(DepthNav::class);
    }

    /** @return array<string, string> Ancestor depth value => dot path to its id on a model at this depth. */
    public function ancestryPaths(): array
    {
        return $this->enumAttribute(DepthAncestry::class)->paths;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function plural(): string
    {
        return $this->label().'s';
    }

    /** @return bool Whether a standing may be held directly at this depth. */
    public function holdsMembers(): bool
    {
        return $this !== self::connection;
    }

    /**
     * @return bool Whether a subject this depth cannot find sends the reader up rather
     *              than telling them there is nothing there.
     */
    public function redirectsWhenAbsent(): bool
    {
        return $this === self::connection;
    }

    public function foreignKey(): string
    {
        return $this->value.'_id';
    }
}
