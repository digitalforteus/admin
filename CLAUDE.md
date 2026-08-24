# CLAUDE.md

## Commands

Always `./vendor/bin/sail …` — bare `sail` is a human shell alias and is not on your PATH.
`composer fix` and `composer check` are slow by design. Give them a 600000 ms timeout and let them finish; a timeout is not a failure to work around.
`check` = lint, rector-lint, analyze, openapi-validate, coverage — in that order. The first four are the gates that matter.

## Layout

Don't search for files — paths are deterministic:
- Views: `resources/views/pages/<route>/index.blade.php` (`pages/index.blade.php` = home). None in views root.
- Shared: `resources/views/components/`, `svg/`, `emails/`.
- Routes: `app/Routes/Web.php` (public pages), `Auth.php`, `Admin.php`, `ApiRoute.php`.
- Tests: `tests/Behavior/{Web,Api}/`, `tests/Feature/`, `tests/Unit/`. One broad test per file — `--filter` takes the class name (`CrawlerTest`, `NavigationTest`, `ComponentTest`), never a guessed one.
- Every page wraps in `<x-main>`; Tailwind + DaisyUI classes only, no custom CSS.
- phpstan runs at max: `config('x')` and `env('x')` return mixed and fail the gate. Use the typed accessor — `Config::string('app.url')`, `Config::array(...)`.

Go straight to the file: read it, then edit. Skip find/ls/glob unless a direct read misses.
A successful edit is confirmation — never re-read to verify, and never re-read after a slow command.

## Adding a page

A page is a standalone Blade file — no controller, no `routes/web.php` entry. Folio routes it by path. The whole shape:

```blade
<?php
use App\Routes\Web;
use Laravel\Head\Facades\Head;

Head::title('About Us')->description('Learn more about us.');
?>
<x-main>
    …
</x-main>
```

Title and description are required — `Head::defaults()` supplies canonical, og and robots. That skeleton is complete; don't read a sibling page to confirm it.

1. Create `resources/views/pages/<slug>/index.blade.php`.
2. Add `case <name> = '/<slug>';` to `app/Routes/Web.php`. Folio derives the URL from the directory name, so the two `<slug>`s are one decision — pick it once and spell it identically, or the route 404s. A case there is sitemapped unless tagged `#[ExcludeFromSitemap]`, and `CrawlerTest` GETs every sitemapped URL expecting 200 — so the view must exist and render for a guest.
3. Link with `Web::<name>->url()`, never a hardcoded path.
4. The home page carries links in two places — the `$siteLinks` array and the Explore card grid. Update both, or neither.

Verify with `sail pest --filter=CrawlerTest` — one run covers the route, the sitemap and the render.

When a suite fails, read the failure that is already on screen. Do not re-run pest to look for it, and do not re-run a passing test to prove the failure was not yours — `check` names the file and line; act on that.

## During Turn Procedure
1. `sail pest --filter=<ClassName>`: scope testing, cheap
2. `sail composer test`: fast project-wide testing, cheap

## End of Turn Procedure

1. `sail composer fix`
2. `sail composer check`
