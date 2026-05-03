# laravel-essentials

Opinionated defaults for Laravel projects. Drop-in framework configuration that flips the safety / strictness / observability switches your team would otherwise rediscover by hand on every new project.

## What this package does

Laravel ships with sensible-but-permissive defaults. `laravel-essentials` opts a project into stricter, safer, more observable behaviour at boot time:

- **Dogma** — applies opinionated framework configuration on every boot (immutable dates, strict models, morph-map enforcement, automatic eager loading, password defaults, etc.)
- **Middleware** — request IDs, security headers, structured request logging, HTTPS redirects, CSP
- **Database commands** — `db:make`, `db:drop`, `db:wait`, `health` for orchestrated deployments
- **Overseer** — runtime introspection of providers, bindings, routes, aliases (useful for diagnostics, `/admin` panels, and tests)
- **Throttler** — small wrapper around Laravel's `RateLimiter` for per-key attempt limiting

Everything is opt-out via config — set a key to `false` and the relevant principle stops applying. Nothing is forced on the application beyond what's in `config/essentials.php`.

## Requirements

- PHP 8.4+
- Laravel 13+

## Installation

```bash
composer require deplox/laravel-essentials
```

The `EssentialsServiceProvider` is auto-discovered via `composer.json` `extra.laravel.providers`.

Publish the config (optional — defaults are reasonable):

```bash
php artisan vendor:publish --tag=essentials-config
```

## Configuration

Default `config/essentials.php`:

| Key                                  | Type     | Default | Effect                                                                                       |
| ------------------------------------ | -------- | ------- | -------------------------------------------------------------------------------------------- |
| `fake_sleep`                         | `bool`   | `true`  | `Sleep::fake()` — tests don't actually wait                                                  |
| `prevent_stray_requests`             | `bool`   | `true`  | `Http::preventStrayRequests()` — un-faked HTTP calls throw in tests                          |
| `force_https`                        | `bool`   | `true`  | `URL::forceHttps()` — generated URLs use `https://`                                          |
| `aggressive_prefetching`             | `bool`   | `true`  | `Vite::useAggressivePrefetching()`                                                           |
| `immutable_dates`                    | `bool`   | `true`  | `Date::use(CarbonImmutable::class)`                                                          |
| `unguard_model`                      | `bool`   | `false` | `Model::unguard()` — disables `$fillable` checks (development convenience, never production) |
| `strict_model`                       | `bool`   | `true`  | `Model::shouldBeStrict()` — errors on lazy load, missing/silent attribute access             |
| `automatic_eager_load_relationships` | `bool`   | `true`  | `Model::automaticallyEagerLoadRelationships()` — defeats N+1 by default                      |
| `require_morph_map`                  | `bool`   | `true`  | `Relation::requireMorphMap()` — class names must be aliased before being persisted           |
| `prohibit_destructive_commands`      | `bool`   | `true`  | `DB::prohibitDestructiveCommands()` in production (blocks `migrate:fresh`, `db:wipe`, etc.)  |
| `set_default_passwords`              | `bool`   | `true`  | `Password::defaults(...)` — min 8, mixed-case + uncompromised in production                  |
| `default_string_length`              | `int`    | `255`   | `Builder::defaultStringLength()` — migration `string()` column length                        |
| `default_morph_key_type`             | `string` | `'int'` | `Builder::defaultMorphKeyType()` — `'int'` or `'uuid'`                                       |
| `log_requests`                       | `bool`   | `false` | Enables the `LogRequests` middleware (the middleware itself must be added to your stack)     |

`EssentialsConfig` is a readonly value object that hydrates from the config array via `EssentialsConfig::fromArray()`. The service provider builds it on every boot and hands it to the `DogmaManager`.

---

## Dogma — opinionated framework configuration

`Dogma\DogmaManager` applies the configuration to the framework. It runs unconditionally in `EssentialsServiceProvider::boot()`, so every request and every Artisan command starts from the same baseline.

The four principles each receive an `EssentialsConfig` and call into framework-level static configuration. Each principle exposes a `status(): array` for diagnostic introspection.

### `HttpPrinciple`

Applies HTTP-layer defaults:

- `Sleep::fake($fakeSleep)`
- `URL::forceHttps($forceHttps)`
- `Http::preventStrayRequests($preventStrayRequests)`
- `Vite::useAggressivePrefetching()` when `aggressive_prefetching` is true

### `ModelPrinciple`

Applies Eloquent-level defaults:

- `Model::unguard($unguardModel)`
- `Model::shouldBeStrict($strictModel)` — the strict-mode triple: prevent lazy loading, prevent silently discarding attributes, prevent accessing missing attributes
- `Model::automaticallyEagerLoadRelationships($automaticEagerLoadRelationships)` (Laravel 12+ feature)
- `Relation::requireMorphMap($requireMorphMap)`

### `DatabasePrinciple`

Applies schema and DB defaults:

- `Builder::defaultStringLength($defaultStringLength)`
- `Builder::defaultMorphKeyType($defaultMorphKeyType)`
- `DB::prohibitDestructiveCommands(true)` — only when `prohibit_destructive_commands` AND `app()->isProduction()` are both true

### `GeneralPrinciple`

Applies cross-cutting defaults:

- `Date::use(CarbonImmutable::class)` when `immutable_dates`
- `Password::defaults(...)` when `set_default_passwords`:
  - Development: `min(8)`
  - Production: `min(8)->mixedCase()->uncompromised()` — last check hits the haveibeenpwned k-anonymity API

### Status snapshot

```php
$dogma = app(DogmaManager::class);

dump($dogma->status());
// [
//   'http'     => ['fakeSleep' => true, 'forceHttps' => true, ...],
//   'model'    => ['preventsLazyLoading' => true, ...],
//   'database' => ['defaultStringLength' => 255, ...],
//   'general'  => ['immutableDates' => true, 'defaultPasswordRules' => true],
// ]
```

Useful for `/health` endpoints or admin dashboards that need to confirm the live application is running with the expected hardening.

### Adding your own principle

Extend `Dogma\Principles\Principle` and call its `apply()` from your own service provider, or fork `DogmaManager`. Principles are intentionally not registered through a registry — each one is an explicit static call site so the boot path remains greppable.

---

## Middleware

All middleware are in `Deplox\Essentials\Middlewares`. Wire them into your stack via `bootstrap/app.php` `withMiddleware()`.

### `UseRequestId`

Generates a lowercase ULID when no `X-REQUEST-ID` header is present, otherwise propagates the incoming value. Stores the chosen ID in Laravel `Context` (so log channels and exception traces pick it up automatically) and echoes it back on the response.

```php
$middleware->appendToGroup('web', [UseRequestId::class]);
$middleware->appendToGroup('api', [UseRequestId::class]);

// Anywhere downstream:
Context::get('requestId'); // → '01arz3ndektsv4rrffq69g5fav'
```

### `UseHeaderGuards`

Sets three security headers on every response:

| Header                      | Value                                                  |
| --------------------------- | ------------------------------------------------------ |
| `X-Frame-Options`           | `SAMEORIGIN`                                           |
| `X-Content-Type-Options`    | `nosniff`                                              |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains; preload` (1 yr) |

### `LogRequests`

Emits one structured `INFO` log entry per request when `config('essentials.log_requests')` is `true`:

```
[INFO] request {"method":"GET","path":"/api/users","status":200,"duration_ms":47}
```

Deliberately does NOT log the IP or user-agent — those belong in your access log, not in the application channel. Add via:

```php
$middleware->appendToGroup('api', [LogRequests::class]);
```

### `ForceHttps`

In production only, returns a `301` redirect to the HTTPS equivalent for any insecure request. Heavier than the URL-generator-level `force_https` config flag — this actually redirects clients.

### `ContentSecurityPolicy`

Builds a `Content-Security-Policy` header from `config('essentials.csp')`:

```php
'csp' => [
    'default-src' => ["'self'"],
    'script-src'  => ["'self'", "https://cdn.example.com"],
    'style-src'   => ["'self'", "'unsafe-inline'"],
],
```

If the config key is empty or missing, the middleware is a no-op — convenient for incremental adoption.

---

## Artisan commands

Registered automatically by the service provider when running in console.

### `health`

```bash
php artisan health
```

Probes the database, cache, and queue connections. Each check logs `INFO` on success or `ERROR` on failure. The command's exit code is `0` if everything passes, `1` otherwise — wire it into your container health probe or deployment smoke test.

### `db:wait`

```bash
php artisan db:wait --connection=default --tries=30 --delay=1
```

Polls the database connection until it answers or `--tries` is exhausted. Use it as a startup-ordering primitive in Docker / Kubernetes so the application doesn't hard-fail before its database is ready.

### `db:make` / `db:drop`

```bash
php artisan db:make my-tenant-db --force
php artisan db:drop my-tenant-db --force
```

Provision and tear down databases through the active connection. Both commands implement `Isolatable` (only one instance runs at a time), use `Confirmable` (interactive Y/n prompt outside production unless `--force`), and `Prohibitable` (can be globally disabled).

`db:drop` on PostgreSQL terminates open connections via `pg_terminate_backend` before issuing `DROP DATABASE` — so it doesn't fail with "database is being accessed by other users".

The actual schema work lives in `Database\Actions\CreateDatabase` and `Database\Actions\DeleteDatabase` — invokable, container-resolved, easily callable outside the command context (e.g., from tenant provisioning code).

---

## Overseer — application introspection

`Overseer\OverseerManager` aggregates eight inspectors that read the live container, router, and environment. Bound as the `'overseer'` singleton in the service provider; access via the `Overseer` facade for ergonomics.

```php
use Deplox\Essentials\Facades\Overseer;

Overseer::environment();   // PHP, Laravel, Composer, DB versions
Overseer::providers();     // registered/deferred providers + what they provide
Overseer::aliases();       // container aliases
Overseer::bindings();      // bindings with resolved/singleton flags
Overseer::instances();     // cached singleton instances
Overseer::extenders();     // container extend() callbacks
Overseer::router();        // every route with method, URI, action, middleware
Overseer::inspect();       // all of the above as a Collection
```

### Inspectors

Eight classes under `Overseer\Inspectors` — `EnvironmentInspector`, `ProvidersInspector`, `AliasesInspector`, `BindingsInspector`, `InstancesInspector`, `ExtendersInspector`, `RouterInspector`. Each takes the `Application` and returns an array. The base `Inspector` class is a thin abstract template — adding a new inspector is one class plus a method on `OverseerManager`.

`RouterInspector` is the heaviest — it normalises closures (`'closure'`), invokables (FQCN), and view/redirect responses, expands middleware aliases, and includes scope bindings, defaults, and lock metadata.

### Where Overseer pays off

- Diagnostics endpoints (`GET /admin/system`)
- Architecture tests (`expect(Overseer::providers())->toHaveKey(...)`)
- Generated API docs and admin panels
- Debugging "why is my container resolving X?" without `dd($app)` recursion blow-ups

---

## Throttler

A small fluent wrapper around `Illuminate\Cache\RateLimiter`. Lives at `Utilities\Throttler`.

```php
use Deplox\Essentials\Utilities\Throttler;

$throttler = new Throttler('login:'.$ip, limit: 5, wait: 60);

$result = $throttler->attempt(fn () => Auth::attempt($credentials));

if ($result === false) {
    abort(429, "Try again in {$throttler->availableIn()}s");
}
```

Public surface:

| Method                                       | Returns | Effect                                                           |
| -------------------------------------------- | ------- | ---------------------------------------------------------------- |
| `attempt(Closure $callback)`                 | `mixed` | Runs callback and counts a hit unless already over the limit      |
| `hit()` / `increment(int)` / `decrement(int)` | `int`   | Adjust counter manually                                          |
| `attempts()`                                 | `int`   | Current count                                                    |
| `remaining()`                                | `int`   | Tries left before lockout                                        |
| `tooManyAttempts()`                          | `bool`  | Already over the limit                                           |
| `clear()` / `resetAttempts()`                | —       | Wipe counter (and lockout for `clear`)                           |
| `availableIn()`                              | `int`   | Seconds until next attempt allowed                               |

The constructor's `$wait` accepts `int` seconds, a `DateInterval`, or a `DateTimeInterface` (treated as an absolute reset time).

---

## Testing patterns

Pest 4. Tests in `tests/Feature/`:

- **Middleware** — construct a `Request`, call `$middleware->handle($request, fn ($r) => new Response)`, assert headers / `Context` state / log spies.
- **Commands** — `$this->artisan(DbWaitCommand::class, [...])->assertSuccessful()` and friends; the package patterns also assert `Isolatable`, `Confirmable`, and `Prohibitable` interfaces are present.
- **Dogma** — a `dogmaConfig(array $overrides = [])` helper builds an `EssentialsConfig` per test; assert `DogmaManager::status()` reflects the overrides.
- **Overseer** — assertions against the array structure of each inspector method; the router inspector test seeds known routes and asserts they appear with the expected shape.
- **Throttler** — exercises the full state machine (`hit` → `tooManyAttempts` → `availableIn` → `clear` → `resetAttempts`).

Several principles touch global state. The tests use `beforeEach()`/`afterEach()` blocks to reset framework defaults (e.g., `Model::unguard(false)` after a test that flipped it).

---

## File layout reference

```
src/
├── Console/
│   └── HealthCommand.php
├── Database/
│   ├── Actions/
│   │   ├── CreateDatabase.php
│   │   └── DeleteDatabase.php
│   └── Commands/
│       ├── DbDropCommand.php
│       ├── DbMakeCommand.php
│       └── DbWaitCommand.php
├── Dogma/
│   ├── DogmaManager.php
│   └── Principles/
│       ├── DatabasePrinciple.php
│       ├── GeneralPrinciple.php
│       ├── HttpPrinciple.php
│       ├── ModelPrinciple.php
│       └── Principle.php           # abstract base
├── Facades/
│   └── Overseer.php
├── Middlewares/
│   ├── ContentSecurityPolicy.php
│   ├── ForceHttps.php
│   ├── LogRequests.php
│   ├── UseHeaderGuards.php
│   └── UseRequestId.php
├── Overseer/
│   ├── Inspectors/                 # 8 inspectors
│   └── OverseerManager.php
├── Utilities/
│   └── Throttler.php
├── EssentialsConfig.php
└── EssentialsServiceProvider.php
config/
└── essentials.php
```
