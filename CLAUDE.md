# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel 12.x application using a custom opinionated framework fork (`jonagoldman/laravel-framework`). PHP 8.4, monorepo structure with three local packages in `packages/`. DDEV-based development environment (`play.ddev.site`).

## Common Commands

```bash
# Development
composer dev              # Start all dev servers (serve, queue, pail, vite)
ddev start                # Start DDEV environment

# Testing (Pest PHP)
composer test             # Clear config cache and run full test suite
php artisan test          # Run tests directly
php artisan test --filter=TestName  # Run a single test

# Code Quality
./vendor/bin/pint         # Fix code style (Laravel Pint)
composer stan             # Run PHPStan (level max)
composer refactor         # Run Rector refactoring

# Frontend
pnpm dev                  # Vite dev server
pnpm build                # Production build
```

## Architecture

### Monorepo Packages (`packages/`)

Three local packages symlinked via Composer path repositories:

- **laravel-auth** — Token-based authentication: `DynamicGuard` (session-first, bearer fallback), `AuthenticateToken` action, `TokenType` enum (`Bearer`, `Remember`). Provides `auth:dynamic` guard. Key trait: `HasTokens`.
- **laravel-support** — Eloquent concerns (`HasExpiration`, `HasSlugs`, `HasChildren/HasParent`, `InMemory`, `CanIncludeRelationships`), custom validation rules (`ExistsEloquent`, `UniqueEloquent`), password reset utilities.
- **laravel-essentials** — Middleware (`UseRequestId`, `UseHeaderGuards`), database commands (`db:make`, `db:drop`), `Overseer` request inspection, `DogmaManager`, `EssentialsConfig`.

### Application Structure

```
app/
├── ApiRouteRegistrar.php      # Invokable route registrar (called via tap())
├── Controllers/               # final readonly, no Http/ subdirectory
├── Models/
│   ├── Builders/              # Custom Eloquent builders
│   ├── Concerns/              # Model traits (CanIncludeRelationships)
│   └── Scopes/                # Global/local scopes
├── Providers/
│   └── AppServiceProvider.php # Routes, morph map, auth config, event listeners
├── Resources/
│   ├── Json/                  # Standard JsonResource classes
│   └── JsonApi/               # JSON:API-style resources
└── Services/                  # Business logic (UserService, TokenService)
```

### Route Registration

Routes are registered in `AppServiceProvider::registerRoutes()` using an invokable class via `tap()`:

```php
$router->prefix('api')
    ->middleware(['api', 'throttle:api'])
    ->group(fn ($router) => $router->tap(new ApiRouteRegistrar));
```

`ApiRouteRegistrar::__invoke()` defines all API routes. Protected routes use `auth:dynamic` middleware with `scopeBindings()`.

### Authentication

Configured in `AppServiceProvider::register()`:

```php
AuthServiceProvider::configure(
    tokenModel: Token::class,
    userModel: User::class,
    guards: ['session'],
    statefulDomains: ['localhost', '...', 'play.ddev.site'],
);
```

**DynamicGuard flow:** Tries each configured guard (session) first. If none authenticate, falls back to bearer token via `AuthenticateToken`. Token format: `{ulid}|{plain}` (e.g., `01J2K3M4N5|abc123...`). Plain text is 48 chars (bearer) or 60 chars (remember). Stored as SHA256 hash. Checks `expires_at`, updates `last_used_at` with debounce, dispatches `TokenAuthenticated` event.

### Middleware Pipeline

In `bootstrap/app.php`, both `web` and `api` groups get:
- `UseRequestId` — Assigns/propagates a request ID via `Context`
- `UseHeaderGuards` — Header-based guard switching

JSON exception rendering forced for `api/*` routes.

### Database

- MariaDB 11.8 via DDEV (host: `db`, credentials: `db/db/db`)
- Tests use SQLite in-memory
- ULID primary keys everywhere (not auto-increment)
- Migration numeric prefixes: `0*` = system tables (jobs, cache), `1*` = domain tables (users, tokens)
- Morph map enforced: `'user' => User::class, 'token' => Token::class`

## Key Patterns

### Controllers

`final readonly` classes with constructor-injected services. Delegate business logic to services, return `->toResource()` or `->toResourceCollection()` (provided by the `#[UseResource]` attribute on models).

```php
final readonly class UserController
{
    public function __construct(private UserService $userService) {}

    public function index(Request $request): ResourceCollection
    {
        return User::query()
            ->withIncluded(allowed: ['tokens'], allowedCounts: ['tokens'])
            ->get()
            ->toResourceCollection();
    }
}
```

### Services

Validate via `Validator::validate()` (throws `ValidationException`). Return Eloquent models.

```php
public function createUser(array $attributes, bool $dispatch = false): User
{
    $validated = Validator::validate($attributes, [
        'name' => ['required', 'string', 'min:2', 'max:190'],
        // ...
    ]);
    return User::query()->create($validated);
}
```

### Resources

Two styles in use:
- **`Resources/Json/`** — Standard `JsonResource` with `toArray()`, `whenLoaded()`, `whenCounted()`
- **`Resources/JsonApi/`** — `JsonApiResource` with `toAttributes()` and `$relationships` property

All use `@mixin` PHPDoc for the underlying model. Some include `with()` for `request_id` metadata via `Context::get('requestId')`.

### Models

Key conventions:
- `#[UseResource(UserResource::class)]` attribute — provides `toResource()` / `toResourceCollection()`
- `casts()` method (not `$casts` property)
- `@property-read` PHPDoc for all attributes
- Common traits: `HasUlids`, `HasFactory`, `CanIncludeRelationships`, `HasTokens`, `Notifiable`

### Relationship Filtering

Controller-defined allowlists (not model-level). Query params: `?include=tokens` and `?with_count=tokens`.

- **On query builder:** `->withIncluded(allowed: [...], allowedCounts: [...])` — `#[Scope]` attribute, eager loads
- **On loaded model:** `->loadIncluded(allowed: [...], allowedCounts: [...])` — lazy loads missing relations

Invalid includes are silently ignored (filtered via `array_intersect`).

## Testing

- **Framework:** Pest 4 with `pest()->extend(TestCase::class)->in('Feature')`
- **Database:** Each feature test file declares `uses(RefreshDatabase::class)` at the top
- **Setup:** `beforeEach()` for shared state, `actingAs($user, 'dynamic')` for session auth
- **Token auth:** `$this->withToken($token->getKey().'|'.$token->plain, 'Bearer')`
- **Factories:** `User::factory()->create()`, `Token::factory()->for($user)->create()`, states like `unverified()`
- **Assertions:** `assertSuccessful()`, `assertUnprocessable()`, `assertJsonPath()`, `assertJsonStructure()`, `assertDatabaseHas()`, `assertJsonValidationErrors()`
- **Organization:** `tests/Feature/` for HTTP/integration, `tests/Unit/` for architecture and isolated logic

## Code Conventions

- **`declare(strict_types=1)`** on every PHP file
- **`final` classes** enforced by Pint
- **ULID primary keys** on all models (`HasUlids` trait)
- **Strict comparisons** (`===`/`!==` only)
- **Multibyte string functions** (`mb_*` instead of `str*`)
- **Immutable DateTime** (`DateTimeImmutable` over `DateTime`)
- **Architecture tests** in `tests/Unit/ArchitectureTest.php` enforce `php`, `security`, and `laravel` presets
- **Class element ordering** (enforced by Pint): use_trait → case → constants → properties → construct → destruct → magic → phpunit → abstract methods → public static → public → protected static → protected → private static → private

## Known Issues

- **PHPStan:** ~30 pre-existing errors unrelated to new work
- **Architecture tests:** 2 pre-existing failures (`HasChildren` uses `debug_backtrace`, `UserController` suffix convention)
- **PHP 8.4 trait conflicts:** Trait properties conflict with `final` class redefinitions — use getters with `??` fallback instead
