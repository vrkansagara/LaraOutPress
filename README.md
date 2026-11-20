<p align="center">
  <a href="https://github.com/vrkansagara/LaraOutPress">
    <img src="Images/LaraOutPress.png?raw=true" alt="LaraOutPress logo" width="480">
  </a>
</p>

<h1 align="center">LaraOutPress</h1>

<p align="center">
  <strong>Lightweight HTML and inline JavaScript minification for Laravel.</strong><br>
  Reduce unnecessary response markup without modifying controllers, routes, or Blade templates.
</p>

<p align="center">
  <a href="https://packagist.org/packages/vrkansagara/lara-out-press"><img src="https://img.shields.io/packagist/v/vrkansagara/lara-out-press" alt="Latest Packagist version"></a>
  <a href="https://github.com/vrkansagara/LaraOutPress/actions/workflows/php.yml"><img src="https://github.com/vrkansagara/LaraOutPress/actions/workflows/php.yml/badge.svg" alt="CI status"></a>
  <a href="https://packagist.org/packages/vrkansagara/lara-out-press"><img src="https://img.shields.io/packagist/php-v/vrkansagara/lara-out-press" alt="PHP requirement"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-BSD--3--Clause-blue" alt="BSD-3-Clause license"></a>
</p>

<p align="center">
  <a href="#quick-start">Quick start</a> ·
  <a href="#configuration">Configuration</a> ·
  <a href="#how-it-works">How it works</a> ·
  <a href="#troubleshooting">Troubleshooting</a> ·
  <a href="#contributing">Contributing</a>
</p>

---

## Overview

**LaraOutPress** is a Laravel package that minifies eligible HTML responses after your application has rendered them. It removes redundant HTML comments and whitespace and performs lightweight minification of inline JavaScript, helping reduce the size of the response body sent to the client.

The package integrates through Laravel middleware, so you can adopt it **without changing existing controllers, routes, or views**.

### Highlights

- **Minimal integration:** Install through Composer and enable with environment configuration.
- **Response-level optimization:** Process rendered HTML instead of changing application templates.
- **Inline JavaScript handling:** Apply a lightweight, rule-based minification pass to inline scripts.
- **Selective execution:** Restrict processing by application environment and request path.
- **Non-HTML protection:** Skip responses with non-HTML content types, including files and binary downloads.
- **Opt-in by default:** Compression is disabled until explicitly enabled.
- **Development visibility:** Optionally display response-size statistics during testing.

> [!NOTE]
> LaraOutPress performs **response-body minification**, not a replacement for transport-level **gzip or Brotli** compression. Actual savings depend on the generated markup and your web-server configuration. Because minification is rule-based, validate rendering and inline scripts before enabling it in production.

## Requirements

- PHP compatible with the version constraints declared in [`composer.json`](composer.json) (CI tests PHP 8.2, 8.3, and 8.4).
- A Laravel application with Composer package discovery enabled, or a manually registered service provider.
- The PHP `zlib` and `pcre` extensions, as declared by the package.

## Quick start

### 1. Install the package

```bash
composer require vrkansagara/lara-out-press
```

Laravel ordinarily discovers the package service provider and facade automatically.

### 2. Enable minification

Add the following settings to the environment configuration loaded by your application:

```dotenv
VRKANSAGARA_COMPRESS_ENABLED=true
VRKANSAGARA_COMPRESS_ENVIRONMENT=production
VRKANSAGARA_COMPRESS_DEBUG=false
```

The package is **disabled by default**. `VRKANSAGARA_COMPRESS_ENABLED=true` must resolve to the PHP boolean `true` in Laravel's configuration; strings such as `"1"` are not equivalent to this strict check.

### 3. Publish configuration (optional)

```bash
php artisan vendor:publish --provider="Vrkansagara\LaraOutPress\ServiceProvider"
```

This creates `config/laraoutpress.php`, where you can customize route exclusions and other options.

If your deployment uses cached Laravel configuration, rebuild the cache **after** changing environment variables or the published config:

```bash
php artisan config:cache
```

Once configured, eligible HTML responses are processed automatically by the package middleware.

## Configuration

The package reads its defaults from `config/laraoutpress.php`. You can configure the main options through environment variables and adjust excluded routes in the published configuration file.

| Environment variable | Config key | Default | Description |
| --- | --- | --- | --- |
| `VRKANSAGARA_COMPRESS_ENABLED` | `enabled` | `false` | Enables or disables response minification. |
| `VRKANSAGARA_COMPRESS_DEBUG` | `debug` | `false` | Adds a visible before/after size comparison to minified HTML pages. |
| `VRKANSAGARA_COMPRESS_ENVIRONMENT` | `target_environment` | `''` | Comma-separated list of permitted application environments. |
| *Configured in PHP only* | `exclude_routes` | `['api/*']` | Request-path patterns excluded from processing. |

### Environment targeting

Run the middleware in one environment:

```dotenv
VRKANSAGARA_COMPRESS_ENVIRONMENT=production
```

Or specify multiple environments:

```dotenv
VRKANSAGARA_COMPRESS_ENVIRONMENT=production,staging,local
```

Use a comma-separated list **without spaces after commas**. The current implementation splits the value on commas and does not trim individual entries.

If `target_environment` is empty or contains an unresolved `${...}` placeholder, the package falls back to its detected `APP_ENV` value. The middleware currently reads this value using PHP's `getenv('APP_ENV')`; ensure the variable is available to the PHP process and verify its value in your deployment. This may differ from `app()->environment()` in some configurations.

> [!TIP]
> Prefer an explicit environment value when configuring this package. Although `vlucas/phpdotenv` supports `${VARIABLE}` interpolation, a reference that cannot be resolved may remain unchanged. Writing the intended environment name directly makes the setting easier to audit.

### Excluding routes

Edit the published `config/laraoutpress.php` file to exclude application paths:

```php
'exclude_routes' => [
    'api/*',
    'webhooks/*',
    'health/*',
],
```

Patterns are evaluated using Laravel's `Request::is()` method. The default configuration excludes `api/*`.

The middleware also skips requests that Laravel identifies as expecting JSON and responses whose content type is not HTML. Non-buffered file responses are not passed into the minification step.

### Debugging response size

Enable the optional size indicator in a local or staging environment:

```dotenv
VRKANSAGARA_COMPRESS_DEBUG=true
```

The package appends an on-screen banner showing the response size before minification, the resulting size, and the calculated percentage difference.

**Do not enable this option for public production pages.** It modifies the rendered response and adds a visible overlay.

## How it works

LaraOutPress registers an HTTP middleware that examines the response after the remainder of the Laravel middleware pipeline has executed.

```text
Incoming HTTP request
        │
        ▼
Laravel application renders a response
        │
        ▼
LaraOutPress middleware
        ├── Disabled or excluded? ────────► Return original response
        ├── Non-HTML / non-buffered? ─────► Return original response
        └── Eligible HTML response
                ├── Minify inline JavaScript
                ├── Remove HTML comments
                ├── Reduce redundant whitespace
                └── Return updated response
        │
        ▼
Browser receives the response
```

When it changes a response body, the middleware removes the existing `Content-Length` header so the serving stack can calculate the correct value.

### Processing boundaries

| Response or request | Behavior |
| --- | --- |
| HTML in an enabled target environment | Minification applied. |
| Inline JavaScript inside eligible HTML | Lightweight rule-based minification applied. |
| Requests that expect JSON | Skipped. |
| Non-HTML response content types (for example PDFs, images or archives) | Skipped. |
| Non-buffered file responses | Skipped. |
| Excluded request paths | Skipped. |
| Disabled or non-matching environment | Returned without minification. |

### Important considerations

Minification is based on regular expressions and string replacements. It is best suited to applications whose output has been tested with those transformations. Pay particular attention to whitespace-sensitive markup, `<pre>` blocks, inline scripts containing complex syntax, and third-party UI components.

The goal is **smaller HTML output with minimal application integration**, not a guarantee of browser performance gains or JavaScript-equivalent transformations in every case. Compare rendered pages and run your normal regression suite before a production rollout.

## Laravel environment files

Laravel normally loads `.env`. However, if `APP_ENV` is supplied by the operating system or through an Artisan `--env` argument, Laravel can attempt to load the corresponding `.env.[APP_ENV]` file when it exists. If that file is absent, Laravel falls back to `.env`.

For example, an externally supplied `APP_ENV=production` can cause Laravel to select `.env.production` when available.

For reliable deployment configuration:

1. Determine which environment file or external variables your application actually uses.
2. Set `VRKANSAGARA_COMPRESS_*` variables in that environment.
3. Rebuild cached configuration after changes.
4. Verify both Laravel's resolved environment and the package's effective configuration.

```bash
php artisan tinker --execute="dump(app()->environment(), getenv('APP_ENV'), config('laraoutpress'));"
```

> [!IMPORTANT]
> When Laravel's configuration is cached, the `.env` file is not loaded during normal application requests. Update the environment configuration **before** running `php artisan config:cache`. See the [Laravel configuration documentation](https://laravel.com/docs/12.x/configuration#configuration-caching).

## Troubleshooting

### Service provider cannot be located

If publishing the configuration produces a class or alias error, confirm the package is installed and Composer's autoloader is current:

```bash
composer show vrkansagara/lara-out-press
composer dump-autoload
php artisan package:discover
```

Also confirm that the dependency is in the application's `require` section if the deployment installs packages with `--no-dev`. If your application has package discovery disabled, register the provider manually:

**Laravel 11 and newer** — `bootstrap/providers.php`:

```php
return [
    // Existing providers...
    Vrkansagara\LaraOutPress\ServiceProvider::class,
];
```

**Laravel 10 and earlier** — `config/app.php` (within `providers`):

```php
Vrkansagara\LaraOutPress\ServiceProvider::class,
```

### HTML is not being minified

Check that:

- `VRKANSAGARA_COMPRESS_ENABLED` resolves to the boolean `true`.
- The current `APP_ENV` matches an entry in `VRKANSAGARA_COMPRESS_ENVIRONMENT`.
- The request does not match an excluded path or expect JSON.
- The response is buffered HTML rather than a file, stream, or non-HTML response.
- The latest configuration has been cached after your changes.

Inspect the loaded configuration using:

```bash
php artisan tinker --execute="dump(config('laraoutpress'));"
```

If configuration appears stale, use `php artisan config:clear` during diagnosis, then regenerate the cache as required by your deployment.

## Development and testing

Clone the repository, install development dependencies, and run the available checks:

```bash
composer install
composer run-script test       # PHPUnit
composer run-script cs-check   # PHP_CodeSniffer
```

The GitHub Actions workflow is configured to run checks on PHP **8.2, 8.3, and 8.4** for pushes and pull requests targeting `master`.

## Contributing

Contributions that improve correctness, compatibility, documentation, and performance are welcome.

- Open an [issue](https://github.com/vrkansagara/LaraOutPress/issues) to report a bug or propose an enhancement.
- Include a minimal reproducible example when reporting unexpected output.
- Add or update tests when submitting behavior changes.
- Run the test suite and coding-standard checks before opening a pull request.

For security issues, follow the private disclosure instructions in [SECURITY.md](SECURITY.md) rather than opening a public issue.

## License

LaraOutPress is open-source software distributed under the [BSD 3-Clause License](LICENSE).

---

<p align="center">
  Developed and maintained with ❤️ in India<br>
  <a href="https://github.com/vrkansagara">GitHub</a> ·
  <a href="https://packagist.org/packages/vrkansagara/lara-out-press">Packagist</a> ·
  <a href="https://vrkansagara.in">Website</a>
</p>
