# Admin

An opinionated Laravel application, and the upstream of every application built from it.

## How the scheme works

This repository is not a starting point you copy and forget. It stays upstream of every
project for the life of that project. Improvements are made here once and merged forward
into each application; each application keeps a small, declared set of files it owns
outright and merges everything else.

That works because the applications share real git ancestry with this repository. There is a
merge base, so git can tell the difference between a file a project *deliberately changed*
and a file the project is simply *behind* on — a distinction no amount of comparing final
states will give you.

Two rules make the merges cheap, and everything else follows from them:

1. **Identity lives in the environment, never in source.** Application name, description,
   logo title, support address, whether the builder attribution renders — all of it is read
   from `.env` through `config/brand.php` and friends. A project that edits a shared file to
   change a string has created a conflict that recurs on every future merge.
2. **A project owns a file, or it merges it. Never both.** Files a project genuinely owns are
   declared once in `.gitattributes` and resolve to the project's side automatically.
   Everything else takes the template's version.

## Starting a new project

Clone this repository rather than copying it. The clone carries the history, which is what
makes every later merge a normal three-way merge.

```shell
git clone https://github.com/digitalforteus/laravel-template.git my-project
cd my-project
php init
```

`init` does the rest of the setup. It asks for the project's slug, name, description, url,
database, admin and support addresses, organisation and author, and shows the values for
confirmation before writing anything. The description becomes an environment value rather
than a string in a source file, which is the pattern for everything it collects. Anything
it does not ask for is environment configuration — set it in `.env`.

With the values confirmed it then, prompting before each step that touches the network or
the working tree:

- renames the clone's `origin` to `template` and creates `<organisation>/<slug>` as a
  private repository with `gh`, so the template stays upstream and the project pushes to
  its own remote;
- sets `merge.keepours.driver` and pins `pull.rebase` to false in the clone's config;
- copies `.env.example` to `.env`, then runs `composer update`, `sail up -d`,
  `sail npm install`, `key:generate`, `composer fix`, `sail npm run build` and
  `composer check`, stopping at the first failure;
- offers to delete itself, commit the result and push it.

It classifies remotes by url rather than by name, so it will not offer to push a project
onto this repository even in a clone whose remotes are wired the wrong way round. Running
it twice is safe: the steps it has already done prompt nothing.

## Pulling template changes into a project

Push the change here first, then, in the project:

```shell
git pull template main
composer check
git push
```

`git pull` on its own goes to `origin` — the project's own repository — and will not see this
one. The template remote has to be named.

If the merge conflicts, the conflicts are the interesting part: they are the files where the
project and the template both moved. Resolve them, run `composer check`, and commit.

Three things go wrong here, all quietly:

- **A merge can bring code that needs a newer locked dependency.** Lockfiles are never
  declared as project-owned, so a merge may leave `composer.lock` behind what the merged code
  requires. It surfaces as an undefined symbol during static analysis, not as a conflict. Run
  `composer update <the-package>` and re-check.
- **Never rebase.** `git pull --rebase template main` replays the project's commits onto this
  repository's history and destroys the ancestry the scheme depends on. Merge only. A global
  `pull.rebase = true` turns the ordinary command into the destructive one.
- **`git pull template main` merges the fetched head** without moving the `template/main`
  tracking ref, so `git log template/main` can look stale afterwards. `git fetch template`
  refreshes it.

## What a project owns

Each project declares its own surface at the bottom of `.gitattributes`:

```
README.md                              merge=keepours
resources/css/app.css                  merge=keepours
resources/views/pages/index.blade.php  merge=keepours
tests/Feature/HomeTest.php             merge=keepours
```

Entries earn their place by being permanently divergent: the palette, the marketing pages and
the tests that describe *that* product rather than this scaffold. A file listed here silently
stops receiving template improvements forever, so the list should be short and every line
should be a decision someone can defend.

Two categories must never appear:

- **Lockfiles.** Resolving one to the project's side pins it below what merged code needs. Let
  them conflict and resolve by regenerating — the only meaningful merge for a lockfile.
- **Anything that is only different because of branding.** That is rule 1 above: move the value
  into the environment and the file stops differing at all.

The mechanism has a sharp edge. The driver named by `merge=keepours` is **not** carried by
`.gitattributes`; it lives in `.git/config` and every clone must set it once:

```shell
git config merge.keepours.driver true
```

Without it git falls back to an ordinary conflicting merge and says nothing, so a clone that
skipped the command looks fine right up until a merge resolves the wrong way.

## Sending an improvement back

Prefer making shared changes here first and merging them down — that way every project gets
them and nothing needs backporting.

When something shared was already built inside a project, lift it up. Shared ancestry means an
ordinary cherry-pick works:

```shell
git remote add my-project https://github.com/digitalforteus/my-project.git
git fetch my-project
git cherry-pick <sha>
composer check
```

Before doing that, check whether the change is really shared. If it names the product, renders
its marketing copy, or exists only because of that project's domain, it belongs to the project.

## Deploying After Check

```shell
gh secret set LARAVEL_CLOUD_DEPLOY_HOOK --body 'https://cloud.laravel.com/deploy/<environment>/<token>'
```
