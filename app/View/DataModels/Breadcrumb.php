<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use App\Helpers\Depth;
use App\Helpers\MemberRole;
use App\Models\User;
use App\Modules\Contexts\Context;
use App\Modules\Contexts\DepthQuery;
use App\Routes\ContextRoute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Zerotoprod\DataModel\Describe;

#[Describe([Describe::nullable => true])]
readonly class Breadcrumb
{
    use DataModel;

    public const string segments = 'segments';

    /** @var list<array<string, mixed>> */
    #[Describe([Describe::default => []])]
    public array $segments;

    public static function current(): ?self
    {
        $User = Auth::user();

        if (! $User instanceof User) {
            return null;
        }

        return self::from([self::segments => self::cascade($User)]);
    }

    /** @return list<BreadcrumbSegment> */
    public function trail(): array
    {
        return array_map(
            static fn (array $segment): BreadcrumbSegment => BreadcrumbSegment::from($segment),
            $this->segments,
        );
    }

    /** @return array<string, mixed> */
    public function props(): array
    {
        return $this->collect()->all();
    }

    /** @return list<array<string, mixed>> */
    private static function cascade(User $User): array
    {
        $segments = [];

        foreach (Depth::chain() as $Depth) {
            $Subject = Context::of($Depth);

            if (! $Subject instanceof Model) {
                $segments[] = self::unsettled($Depth, $User);

                break;
            }

            $segments[] = self::settled($Depth, $Subject, $User);
        }

        return $segments;
    }

    /** @return array<string, mixed> */
    private static function settled(Depth $Depth, Model $Model, User $User): array
    {
        $parameters = self::parameters($Depth, $Model);
        $slug = self::slug($Model);

        return [
            BreadcrumbSegment::label => self::name($Model),
            BreadcrumbSegment::url => ContextRoute::of($Depth)->url($parameters),
            BreadcrumbSegment::picture => self::picture($Model),
            BreadcrumbSegment::fallback => $Depth->icon(),
            BreadcrumbSegment::switchLabel => 'Switch '.$Depth->value,
            BreadcrumbSegment::settingsUrl => self::settings($Depth, $parameters),
            BreadcrumbSegment::settingsLabel => $Depth->label().' settings',
            BreadcrumbSegment::createUrl => self::createUrl($Depth),
            BreadcrumbSegment::createAction => null,
            BreadcrumbSegment::createFields => [],
            BreadcrumbSegment::createLabel => 'New '.$Depth->value,
            BreadcrumbSegment::items => self::items(
                $Depth,
                DepthQuery::children($Depth, self::above($Depth), $User),
                $slug,
            ),
        ];
    }

    /** @return array<string, mixed> */
    private static function unsettled(Depth $Depth, User $User): array
    {
        return [
            BreadcrumbSegment::label => 'Select '.$Depth->value,
            BreadcrumbSegment::url => null,
            BreadcrumbSegment::picture => null,
            BreadcrumbSegment::fallback => $Depth->icon(),
            BreadcrumbSegment::switchLabel => $Depth->plural(),
            BreadcrumbSegment::settingsUrl => null,
            BreadcrumbSegment::settingsLabel => '',
            BreadcrumbSegment::createUrl => self::createUrl($Depth),
            BreadcrumbSegment::createAction => self::createAction($Depth),
            BreadcrumbSegment::createFields => BreadcrumbFields::of($Depth),
            BreadcrumbSegment::createLabel => 'New '.$Depth->value,
            BreadcrumbSegment::items => self::items(
                $Depth,
                DepthQuery::children($Depth, self::above($Depth), $User),
                null,
            ),
        ];
    }

    /**
     * @param  list<Model>  $Models
     * @return list<array<string, mixed>>
     */
    private static function items(Depth $Depth, array $Models, ?string $except): array
    {
        $items = [];

        foreach ($Models as $Model) {
            if (self::slug($Model) === $except) {
                continue;
            }

            $items[] = [
                BreadcrumbItem::label => self::name($Model),
                BreadcrumbItem::url => ContextRoute::of($Depth)->url(self::parameters($Depth, $Model)),
                BreadcrumbItem::picture => self::picture($Model),
                BreadcrumbItem::fallback => $Depth->icon(),
            ];
        }

        return $items;
    }

    /** The inline form is offered only where the write is one box and the reader may send it. */
    private static function createAction(Depth $Depth): ?string
    {
        if (BreadcrumbFields::of($Depth) === [] || ! self::mayCreate($Depth)) {
            return null;
        }

        return ContextRoute::collection($Depth)->url(ContextRoute::parameters());
    }

    private static function createUrl(Depth $Depth): ?string
    {
        if (BreadcrumbFields::of($Depth) !== [] || ! self::mayCreate($Depth)) {
            return null;
        }

        return ContextRoute::create($Depth)->url(ContextRoute::parameters());
    }

    private static function mayCreate(Depth $Depth): bool
    {
        $Above = $Depth->parent();

        if (! $Above instanceof Depth) {
            return true;
        }

        $Held = Context::role($Above);

        return $Held instanceof MemberRole && $Held->atLeast(self::grants($Depth));
    }

    private static function grants(Depth $Depth): MemberRole
    {
        return $Depth === Depth::connection ? MemberRole::owner : MemberRole::admin;
    }

    /** @param  array<string, string|int>  $parameters */
    private static function settings(Depth $Depth, array $parameters): ?string
    {
        $Held = Context::role($Depth->holdsMembers() ? $Depth : Depth::project);

        if (! $Held instanceof MemberRole || ! $Held->atLeast(self::configures($Depth))) {
            return null;
        }

        return ContextRoute::settings($Depth)->url($parameters);
    }

    private static function configures(Depth $Depth): MemberRole
    {
        return $Depth === Depth::project ? MemberRole::admin : MemberRole::owner;
    }

    private static function above(Depth $Depth): ?Model
    {
        $Above = $Depth->parent();

        return $Above instanceof Depth ? Context::of($Above) : null;
    }

    /** @return array<string, string|int> */
    private static function parameters(Depth $Depth, Model $Model): array
    {
        $parameters = [];

        foreach (array_reverse($Depth->ancestry()) as $Each) {
            if ($Each === $Depth) {
                $parameters[$Each->value] = self::slug($Model) ?? '';

                continue;
            }

            $Subject = Context::of($Each);

            if ($Subject instanceof Model) {
                $parameters[$Each->value] = self::slug($Subject) ?? '';
            }
        }

        return $parameters;
    }

    private static function slug(Model $Model): ?string
    {
        $slug = $Model->getAttribute('slug');

        return is_string($slug) ? $slug : null;
    }

    private static function name(Model $Model): string
    {
        $name = $Model->getAttribute('name');

        return is_string($name) ? $name : '';
    }

    private static function picture(Model $Model): ?string
    {
        return method_exists($Model, 'iconUrl') ? $Model->iconUrl() : null;
    }
}
