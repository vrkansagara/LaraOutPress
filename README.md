<p align="center">
  <img src="Images/LaraOutPress.png?raw=true" alt="LaraOutPress" width="480">
</p>

<h1 align="center">LaraOutPress</h1>
<p align="center"><b>Compress your Laravel application's HTML/JS output before it hits the wire — zero controller changes required.</b></p>

<p align="center">
  <a href="https://packagist.org/packages/vrkansagara/lara-out-press"><img alt="Packagist" src="https://img.shields.io/packagist/v/vrkansagara/lara-out-press"></a>
  <a href="https://github.com/vrkansagara/LaraOutPress/actions/workflows/php.yml"><img alt="Build" src="https://github.com/vrkansagara/LaraOutPress/actions/workflows/php.yml/badge.svg"></a>
  <img alt="PHP" src="https://img.shields.io/badge/php-%5E8.2%20%7C%20%5E8.3%20%7C%20%5E8.4-777bb4">
  <img alt="License" src="https://img.shields.io/badge/license-BSD--3--Clause-blue">
</p>

LaraOutPress is a single `after` middleware. It sits at the end of the pipeline, strips
unnecessary whitespace and comments out of your rendered HTML response, does a light
pass of inline JavaScript minification, and hands the browser a smaller payload — with
zero changes to your controllers, views, or routes.

---

## Installation

```bash
composer require vrkansagara/lara-out-press
```

Laravel's package auto-discovery registers the service provider and facade for you.
If your app has auto-discovery disabled, add the provider manually in `bootstrap/providers.php`
(Laravel 11+) or `config/app.php` (Laravel ≤10):

```php
Vrkansagara\LaraOutPress\ServiceProvider::class,
```

Publish the config file so you can override the defaults:

```bash
php artisan vendor:publish --provider="Vrkansagara\LaraOutPress\ServiceProvider"
```

This creates `config/laraoutpress.php` in your app.

> **"Unable to locate class or alias" when running `vendor:publish`?**
> That means Composer never registered the package in *this* app. Check, in order:
> 1. `composer show vrkansagara/lara-out-press` — confirms it's actually installed (not just listed in `composer.json`).
> 2. It's under `require`, not `require-dev`, if you run `composer install --no-dev` anywhere (CI, production deploys).
> 3. Re-run `composer dump-autoload` after installing — discovery is regenerated at that point.
> 4. Clear stale discovery caches: `php artisan package:discover` — or delete `bootstrap/cache/packages.php` and `bootstrap/cache/services.php` and let Laravel rebuild them.

---

## Configuration

Every setting is controlled through environment variables, with `config/laraoutpress.php`
as the published override point.

| Env variable                        | Config key            | Default | Purpose                                                   |
|--------------------------------------|------------------------|---------|-------------------------------------------------------------|
| `VRKANSAGARA_COMPRESS_ENABLED`        | `enabled`              | `false` | Master on/off switch. Must be a real boolean.                |
| `VRKANSAGARA_COMPRESS_DEBUG`          | `debug`                | `false` | Appends a before/after/percent-saved banner to every page.    |
| `VRKANSAGARA_COMPRESS_ENVIRONMENT`    | `target_environment`   | `''`    | Comma-separated list of environments compression runs in.     |
| *(config only)*                       | `exclude_routes`       | `['api/*']` | Route patterns (as passed to `Request::is()`) that are never compressed. |

### Enable it

```dotenv
VRKANSAGARA_COMPRESS_ENABLED=true
```

Only the literal boolean `true` enables the middleware. Anything else — an empty
string, `"1"`, a stray typo — is treated as `false` on purpose, so a misconfigured
value fails safe instead of silently compressing (or not compressing) unexpectedly.

### Choose which environment(s) it runs in

```dotenv
# Single environment
VRKANSAGARA_COMPRESS_ENVIRONMENT=production

# Multiple environments
VRKANSAGARA_COMPRESS_ENVIRONMENT=production,staging,local
```

> **Don't reference `${APP_ENV}` in your `.env` file.** A line like
> `VRKANSAGARA_COMPRESS_ENVIRONMENT="${APP_ENV}"` looks natural (avoid repeating the
> value), but plain `.env` files are parsed by [`vlucas/phpdotenv`](https://github.com/vlucas/phpdotenv),
> which does **not** expand `${VAR}` references the way a shell or Docker Compose
> does. You'd get the literal 10-character string `${APP_ENV}` instead of `local` —
> and compression would silently never activate. Write the actual environment name.
>
> As a safety net, LaraOutPress falls back to the app's *current* environment
> whenever `target_environment` is left empty or still contains an unresolved
> `${...}` placeholder, so this mistake now fails safe rather than silently.

### See the impact on every page

```dotenv
VRKANSAGARA_COMPRESS_DEBUG=true
```

Appends a fixed banner to the bottom of every compressed page showing the response
size before and after compression, and the percentage saved. Turn this off in
production — it's a development/staging aid, not a user-facing feature.

### Exclude routes

Compression is skipped automatically for JSON responses (`$request->expectsJson()`)
and for any route matching a pattern in `exclude_routes` (published in
`config/laraoutpress.php`, matched via Laravel's `Request::is()`):

```php
// config/laraoutpress.php
'exclude_routes' => [
    'api/*',
    'webhooks/*',
],
```

---

## Which `.env` file actually applies?

Laravel only loads **one** `.env` file per request by default — the plain `.env` at
your project root — regardless of how many environment-specific files
(`.env.local`, `.env.production`, `.env.dusk.local`, …) sit next to it. Those extra
files are conventions from other tools (Vite, Dusk, deployment scripts) and are
**not** read by Laravel/`vlucas/phpdotenv` automatically unless your app explicitly
opts in via `$app->loadEnvironmentFrom(...)` or the `--env` artisan flag.

In practice, that means:

- If you have both `.env` and `.env.production` and you deploy without changing how
  the app boots, **`.env` wins** — `.env.production` is inert dead weight.
- `APP_ENV` itself is read from `.env`, so it can't be used to *choose* which `.env`
  file loads unless something upstream (your deploy pipeline, `php artisan serve
  --env=`, a custom bootstrapper) sets `APP_ENV` **before** Laravel boots and points
  it at a different file.
- When in doubt, dump what Laravel actually resolved instead of guessing from the
  file list:

  ```bash
  php artisan tinker --execute="echo app()->environment();"
  ```

If your deployment relies on multiple `.env.*` files being auto-selected, that
selection logic lives in your own `bootstrap/app.php` / deployment tooling, not in
Laravel core or in this package — make sure `VRKANSAGARA_COMPRESS_*` variables are
set in whichever file actually gets loaded for that environment.

---

## What gets touched

- HTML whitespace between tags is collapsed.
- HTML comments (`<!-- ... -->`) are stripped.
- Inline `<script>` content goes through a light JS-comment/whitespace minifier.
- JSON responses, excluded routes, and non-matching environments are always passed
  through untouched.

## Development

```bash
composer install
composer run-script test      # PHPUnit
composer run-script cs-check  # PHP_CodeSniffer
```

CI runs the full suite across PHP 8.2, 8.3, and 8.4 on every push and pull request.

## Contributing

Issues and pull requests are very welcome — see [SECURITY.md](SECURITY.md) for how
to report a vulnerability privately.

<p align="center">Made with :heart: in India &nbsp; <img src="Images/India.svg" width="16" height="16" valign="middle"></p>
