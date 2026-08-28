# The plugin pattern in `app/Plugins`

What the extraction of `AdminLink` established, where it falls short of a plugin
loader, and the rules that would keep an edit from quietly undoing it.

## What makes it a plugin

Five properties. The package holds all five.

| Property | Where it lives | Status |
|---|---|---|
| **Contract** — the host depends on an interface, never a class | [`DescribesPlugin`](../app/Plugins/DescribesPlugin.php) | Yes |
| **Inverted control** — the host owns the mechanism, the plugin owns the policy | [`RouteTags`](../app/Plugins/RouteTags.php) does the one reflection sweep; the plugin says only which attribute and how to order | Yes |
| **Independently addable unit** — a new capability is a new directory, with no host edit | [`app/Plugins/AdminLink/`](../app/Plugins/AdminLink) | Yes |
| **Declarative registration** | one case on [`PluginIndex`](../app/Plugins/PluginIndex.php) | Yes, hand-maintained by choice, as [`RouteIndex`](../app/Routes/RouteIndex.php) is |
| **Consumer indirection** — the consumer resolves through the registry | the links page iterates `PluginIndex::cases()` and names no plugin | Yes |

The last row is the one that decides the label, and it is the one that was missing
until the page was changed to loop the registry:

```php
@foreach(PluginIndex::cases() as $Plugin)
    @foreach($Plugin->routes() as $link)
        {{-- … --}}
    @endforeach
@endforeach
```

Installing a plugin is now one case on the registry, and the page carries it with
no further edit — which is what separates a **loader** from an **extension point**.
`AdminTest` walks the same two loops, so a plugin that is installed and rendered
nowhere fails the suite rather than passing unnoticed.

One caveat survives it. With a single implementation the contract is unproven:
`routes(): list<{name, url}>` is still AdminLink's own shape rather than an
abstraction two plugins agreed on. The second plugin is what tells you whether the
interface was right; expect to change it then, and prefer changing it to widening it.

## Rules that keep it honest

None of the twelve rules `ZeroToProd\LaravelRector` ships can express any of this.
They are configured application-wide, and every plugin invariant is scoped to a
path, so each of these is a custom `AbstractRector` gated on
`$this->file->getFilePath()`.

One mechanic makes them cheap to write: `composer check` runs `rector --dry-run`,
so a rule that *would* leave a comment fails the gate without ever rewriting
anything. Report by leaving a `TODO`, exactly as `ForbidKeywordUsageRector` does.

Ranked by what they buy against what they cost.

### 1. `ForbidNamespaceDependencyRector` — written and enforced

Files in the root of `app/Plugins/` — `RouteTags`, `TaggedRoute`,
`DescribesPlugin` — may not name `App\Plugins\<Name>\*`.

This is the invariant the pattern is: dependency points inward, and only
`PluginIndex` knows an implementation. Rather than a plugin-specific rule it was
written generally and lives in `zero-to-prod/laravel-rector`, because the same
invariant is what a layer and a module are: a namespace, keyed to the namespaces
it must not name, with the classes allowed to know both sides named as exceptions.

It is registered in `rector.php` and runs on every `fix` and `check`:

```php
->withConfiguredRule(ForbidNamespaceDependencyRector::class, [
    ForbidNamespaceDependencyRector::DEPENDENCIES => [
        'App\\Plugins' => ['App\\Plugins\\*'],
    ],
    ForbidNamespaceDependencyRector::EXCEPT => [
        PluginIndex::class,
    ],
])
```

A violation stops the gate naming both sides and the line:

```
App\Plugins must not name App\Plugins\AdminLink\AdminLinkPlugin. See app/Plugins/RouteTags.php:6
```

A pattern is read against what it constrains: `App\Plugins\*` on the left of a
direction is every namespace below `App\Plugins` and not `App\Plugins` itself, so
the host is forbidden its plugins while the files sharing its root go on naming
each other. On the `except` side the same pattern is read against a class.

### 2. `EnforceRegisteredClassRector` — written and enforced

Every class implementing `DescribesPlugin` is named by `PluginIndex`.

Catches the plugin written and never installed — the half of the promise nothing
else keeps. It generalized the same way rule 1 did: a registry, and what it
registers, named as a contract the class declares, a namespace it is filed under,
or a suffix it is named with. It also ships in `zero-to-prod/laravel-rector`.

```php
->withConfiguredRule(EnforceRegisteredClassRector::class, [
    EnforceRegisteredClassRector::REGISTRIES => [
        [
            EnforceRegisteredClassRector::REGISTRY => PluginIndex::class,
            EnforceRegisteredClassRector::IMPLEMENTS => DescribesPlugin::class,
        ],
    ],
])
```

```
App\Plugins\Sitemap\SitemapPlugin is not registered on App\Plugins\PluginIndex. See app/Plugins/Sitemap/SitemapPlugin.php:7
```

The registry is read by reflection — its enum cases, its constants and its default
property values, arrays among them flattened — so it answers for a file it is not
in, and a registry that keeps its list some other way (a `const` array, the
property a server lists tools on) is read just as well. What the class declares is
read from the declaration, so a contract reached through a parent is seen only
where the parent itself is what `implements` names.

The converse — a registry case pointing at a class that no longer exists — is left
to phpstan, which already fails on `case x = Gone::class;`.

### 3. `ForbidClassDependencyRector` — written and enforced

No file under `App\Plugins\*` names `Reflection*`.

`RouteTags` at the package root is the one place reflection belongs; a plugin
asks it for what it needs rather than sweeping its own attributes. Generalized as
`ForbidNamespaceDependencyRector`'s sibling — same `dependencies`/`except` shape,
same package — but matched against a used class's full resolved name rather than
just its namespace, which is what lets one pattern reach a family like PHP's own
reflection classes that share a name prefix and no namespace at all.

```php
->withConfiguredRule(ForbidClassDependencyRector::class, [
    ForbidClassDependencyRector::DEPENDENCIES => [
        'App\\Plugins\\*' => ['Reflection*'],
    ],
])
```

```
App\Plugins\Probe must not name ReflectionClass. See app/Plugins/Probe/ProbeReflection.php:5
```

`App\Plugins\*` excludes `App\Plugins` itself by the same boundary rule as rule 1,
so `RouteTags` — declared directly in `App\Plugins`, not below it — needed no
exception to keep doing the sweep.

Building it turned up that the two rules are the same shape wearing two different
granularities — a used class compared by its namespace, or by its full name — so
the shared plumbing (reading the configuration, tracking which namespace a
statement is in, walking a statement for the names it resolves, throwing or
leaving a comment) was extracted into an internal base both extend, leaving each
concrete rule only the one method that differs. That refactor also caught a real
gap in the package's own tooling: the `api` MCP tool rendered a rule's `extends`
clause with the parent's full name, which would have printed the internal base's
namespace segment into what is meant to be nothing but the public surface. Fixed
there rather than avoided here, since hiding the abstraction would have cost more
than fixing the one place it leaked.

### 4. `ConsumersUseRegistryRector`

Outside `app/Plugins/` and `tests/`, no file names a concrete `*Plugin` class:
a page asks `PluginIndex`.

Must allow the attribute classes. [`Web`](../app/Routes/Web.php),
[`Admin`](../app/Routes/Admin.php) and [`ApiRoute`](../app/Routes/ApiRoute.php)
legitimately name `AdminLink` — that is how a case is tagged. Forbidding the
plugin class while permitting the attribute is the whole subtlety of the rule.

### 5. `PluginShapeRector`

Each `app/Plugins/<Name>/` holds one `#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]`
class and one `final readonly <Name>Plugin implements DescribesPlugin`, and
nothing else.

Keeps controllers, queries and views out of a plugin directory. The most
prescriptive of the five and the one most likely to be wrong about a plugin
nobody has written yet, so write it last, if at all.

## Running against the working copy

The rule ships in `zero-to-prod/laravel-rector`, and this project reads it from the
checkout at `~/dev/zero-to-prod/laravel-rector` rather than from a release: a path
repository in `composer.json`, required as `dev-main`.

```json
"repositories": [
    {"type": "path", "url": "../../zero-to-prod/laravel-rector", "options": {"symlink": false}}
]
```

`symlink: false` is what makes it work under Sail. The container mounts this project
and nothing above it, so a symlink out of `vendor/` points at a path that does not
exist inside it; mirroring copies the package in, where the mount already reaches.
The cost is that the copy is a snapshot: a change to the package reaches this project
on the next `composer update zero-to-prod/laravel-rector`, not on save.

Both `composer.json` and `composer.lock` now name a path only this machine has.
Point the constraint back at a release before either is deployed from.

## Two decisions before writing any of them

**Where the rule classes live.** `app/Rector/`, which `phpunit.xml` already
excludes from coverage — the 100% gate would otherwise demand tests for a class
no booted application reaches. Better still is where rule 1 went: a rule that
states an invariant rather than a preference belongs in the package, where it is
tested against its own fixtures and every project gets it.

**Whether rule 2 should be a rule at all.** The same guarantee is a few lines in
[`NavigationTest`](../tests/Feature/NavigationTest.php), which already asserts
that every registry case resolves to a `DescribesPlugin`: glob `app/Plugins/*/`,
compare the directories found against `PluginIndex::cases()`, and fail on either
difference. It is a fraction of the effort and it is available this turn. The
rule earns its keep only if you want the failure at edit time rather than at
suite time.
