# Laravel Template

An opinionated Laravel application.

Create a project from this template, then configure its identity:

```shell
php init
```

## Deploying After Check

```shell
gh secret set LARAVEL_CLOUD_DEPLOY_HOOK --body 'https://cloud.laravel.com/deploy/<environment>/<token>'
```
