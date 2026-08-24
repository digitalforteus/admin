# CLAUDE.md

Three rules that this project's transcripts show being broken every time:

- **Never probe.** No `find`, `ls`, `glob` or `grep` to learn whether a path exists or what lives near it. `Read` reports a missing file, `Write` creates one, and an empty directory left by earlier work tells you nothing. A directory path is not a file: `Read` on one is an error, never a listing.
- **Green gates end the turn.** After `check` passes there is no pest run, no `composer test`, no `artisan route:list`, no read-back of your own writes.

## Commands

Always `./vendor/bin/sail …` — bare `sail` is a human shell alias and is not on your PATH.
`composer fix` and `composer check` are slow by design. Give them a 600000 ms timeout and let them finish; a timeout is not a failure to work around.
`check` = lint, rector-lint, analyse, openapi-validate, coverage — in that order. The first four are the gates that matter. The phpstan script is `analyse`; `analyze` is not a script. When coverage dies on `[Tia mode] requires [git]`, re-run the four in one call rather than four: `sail composer lint && sail composer rector-lint && sail composer analyse && sail composer openapi-validate`.

## Layout

Don't search for files — paths are deterministic:
- Views: `resources/views/pages/<route>/index.blade.php` (`pages/index.blade.php` = home). None in views root.
- Shared: `resources/views/components/`, `svg/`, `emails/`.
- Routes: `app/Routes/Web.php` (public pages), `Auth.php`, `Admin.php`, `ApiRoute.php`.
- Admin pages: `resources/views/pages/admin/<slug>/index.blade.php`, case in `app/Routes/Admin.php` as `self::prefix.'/<slug>'`, tagged `#[AdminLink]` to appear in the admin link index. Not sitemapped — `CrawlerTest` never reaches them; `AdminTest` covers them.
- API endpoints: one directory per operation, `app/Modules/Api/<Api>/<Thing>/<Action>/` holding Controller, Request, Response, Schema. Bound in `routes/api.php`, `api_auth.php` or `api_admin.php` as `Route::<verb>(<Enum>::<case>->value, Controller::class)`.
- Admin form actions: `app/Modules/Admin/<Thing>/<Action>Controller.php` (`__invoke`, `readonly`, returns `RedirectResponse`), bound in `routes/web_admin.php` as `Route::<verb>(Admin::<case>->value, Controller::class)`.
- Column schemas: `app/Sources/Db/App/<Table>.php` — a case per column, `->value` for the name, `->schema()` for the API schema. One per table in the app database, so the file for a table exists without looking.
- Test helpers: `tests/Pest.php` — `adminUser()` and friends live there, not in the test file using them.
- `app/Helpers/CacheKey.php` enumerates the cache keys the app owns; a key a user supplies is a plain string and belongs to no enum.
- Migrations answer nothing a `Sources` enum does not; read `app/Sources/Db/App/<Table>.php` instead.
- Tests: `tests/Behavior/{Web,Api}/`, `tests/Feature/`, `tests/Unit/`. `--filter` takes the class name (`CrawlerTest`, `NavigationTest`, `ComponentTest`), never a guessed one. A file's existing coverage is one long `test()` — that is how it is read, not a rule to extend it.
- Every page wraps in `<x-main>`; Tailwind + DaisyUI classes only, no custom CSS.
- phpstan runs at max: `config('x')` and `env('x')` return mixed and fail the gate. Use the typed accessor — `Config::string('app.url')`, `Config::array(...)`.

Go straight to the file: read it, then edit. Skip find/ls/glob unless a direct read misses. Every command starts at the project root, so `pwd` answers nothing. Never probe to learn whether something exists — `Write` creates what is missing and `Read` reports what is not there, while a directory left empty by earlier work answers nothing.
A successful edit is confirmation — never re-read to verify, and never re-read after a slow command. Anchor `old_string` on the single line being changed, not a block of neighbours — a rejected edit costs the retry plus the re-read that follows it.

## Adding a page

A page is a standalone Blade file — no controller, no `routes/web.php` entry. Folio routes it by path.

Title and description are required — `Head::defaults()` supplies canonical, og and robots. That skeleton is complete; don't read a sibling page to confirm it.

1. Create `resources/views/pages/<slug>/index.blade.php`.
2. Add `case <name> = '/<slug>';` to `app/Routes/Web.php`. Folio derives the URL from the directory name, so the two `<slug>`s are one decision — pick it once and spell it identically, or the route 404s. A case there is sitemapped unless tagged `#[ExcludeFromSitemap]`, and `CrawlerTest` GETs every sitemapped URL expecting 200 — so the view must exist and render for a guest.
3. Link with `Web::<name>->url()`, never a hardcoded path.
4. The home page carries links in two places — the `$siteLinks` array and the Explore card grid. Update both, or neither.

Verify with `sail pest --filter=CrawlerTest` — one run covers the route, the sitemap and the render.

When a suite fails, read the failure that is already on screen. Do not re-run pest to look for it, and do not re-run a passing test to prove the failure was not yours — `check` names the file and line; act on that.

## Which layer

A form on an admin page posts to an admin form action and redirects — that is `app/Modules/Admin/`, below. A JSON endpoint is `app/Modules/Api/`. Pick from the deliverable and build only that one; reading the other layer to compare is what turns a 6-call task into a 15-call one.

## Adding an admin form action

Five files, and no others: `app/Routes/Admin.php`, `routes/web_admin.php`, the new page, the new controller and request, `tests/Behavior/Web/AdminTest.php`.

1. `resources/views/pages/admin/<slug>/index.blade.php` — the form posts to `Admin::<case>->url()` with `@csrf`.
2. **One** case in `app/Routes/Admin.php`, serving both verbs: Folio renders the page on GET, `web_admin.php` binds the POST to the same path. A second case for the action (`/cache/add` beside `/cache`) is the wrong shape — `#[AdminLink]` if an admin should find it.
3. `app/Modules/Admin/<Thing>/<Action>Controller.php`: validate, act, `return back()->with('status', '…')`. It is always `back()`, never `redirect(<case>)`, so the test asserts a bare `assertRedirect()` with no argument — asserting a URL against `back()` fails with the app root as the actual value.
4. Bind it in `routes/web_admin.php`.
5. Cover it in `tests/Behavior/Web/AdminTest.php`, in the same turn — guest redirects, non-admin forbidden, admin succeeds, and the effect asserted, addressing fields as `<Action>Request::<field>` rather than raw strings. `Cache::forget()` every key the test sets, in the test as you write it — pest runs parallel against one shared cache store, and a key left behind fails an unrelated assertion in another worker. Coverage cannot flag its absence while Tia is broken here, so this checklist is the only thing standing between you and shipping an untested action.

## Adding an API endpoint

Call `mcp__project__scaffold-endpoint` — it writes the route case, request DTO, response DTO, schema, controller and test together, already matching the conventions, with a marker where a decision is left. Fill in the action body and the test assertions; that is the whole job. Hand-writing the four files instead is the mistake that costs the most: it reads a sibling module to copy the shape, then pays several `check` cycles for what the generator gets right the first time. `dry_run` shows the artifacts without writing them. `Write` creates missing directories — never `mkdir` first.

The DTOs have no constructor: properties are declared with `#[Request]`/`#[Response]` plus the `DataModel` and `Has*Schema` traits, built with `::from($array)`, returned through `api_response()->created(...)`. Declare only the statuses a test reaches — `openapi:coverage` fails a declared response nothing exercises.

## During Turn Procedure
1. `sail pest --filter=<ClassName>`: scope testing, cheap. Pick the class that actually covers what changed — an admin page is `AdminTest`, an API endpoint is its own `tests/Behavior/Api/` file. Skip this run entirely if `check` is next: `check` runs the whole suite.
2. `sail composer test`: fast project-wide testing, cheap

## End of Turn Procedure

1. `sail composer fix`
2. `sail composer check`
3. Stop. Report what you built and what the gates said, from what you wrote rather than from reading it back.

`fix` first, always: `check` opening with a pint style failure is five minutes spent on what `fix` resolves silently. Run both bare — piping either through `grep -A` throws away the verdict. `check`'s coverage step demands 100%, so an API endpoint without its test fails there; `Pest\Exceptions\MissingDependency: [Tia mode] requires [git]` is that step failing on the container rather than on your change, and the four gates before it are the verdict. Once they pass you are done — unless `composer test` is red, which the gates do not cover and coverage cannot report while Tia is broken. A red parallel suite is unfinished work, not an environment quirk.
