# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel 13.x application using a custom opinionated framework fork (`deplox/laravel-framework`, branch `13.x-opinionated`). PHP 8.5. Consumes three external Composer packages from `github.com/deplox`. DDEV-based development environment (`play.ddev.site`).

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

### deplox/* packages (external)

Three Composer packages pulled from `github.com/deplox` via VCS repositories declared in `composer.json`. Pinned to `^0.1`:

- **laravel-shield** (`deplox/laravel-shield`) — Token-based authentication with `Shield` as the central entry point (configuration, boot logic, token-prefix methods). `DynamicGuard` (session-first, bearer fallback), `AuthenticateToken` action, `TokenType` enum (`Bearer`, `Remember`). Provides `auth:dynamic` guard. Key trait: `HasTokens`. Namespace: `Deplox\Shield`.
- **laravel-support** (`deplox/laravel-support`) — Eloquent concerns (`HasExpiration`, `HasSlugs`, `HasChildren/HasParent`, `InMemory`, `CanIncludeRelationships`), custom validation rules (`ExistsEloquent`, `UniqueEloquent`), password reset utilities. Namespace: `Deplox\Support`.
- **laravel-essentials** (`deplox/laravel-essentials`) — Middleware (`UseRequestId`, `UseHeaderGuards`), database commands (`db:make`, `db:drop`), `Overseer` request inspection, `DogmaManager`, `EssentialsConfig`. Namespace: `Deplox\Essentials`.

#### Local-dev workflow for the deplox packages

Clones live at `~/Code/deplox/laravel-{shield,support,essentials}` and are bind-mounted into the DDEV web container at `/var/www/html/.deplox-link` via `.ddev/docker-compose.deplox.yaml`.

- `ddev composer use-local-deplox` — switches `composer.json` to path repositories pointing at `.deplox-link/laravel-*` with `@dev` constraints. Edits in `~/Code/deplox/laravel-shield/src/...` show up live in `vendor/` (symlinked). Backs up the released-mode `composer.json` to `composer.json.bak`.
- `ddev composer use-released-deplox` — restores `composer.json` from the backup and runs `composer update` so the packages return to their tagged release.
- Cut a new release: tag `vX.Y.Z` in the package repo, push the tag, then `ddev composer require deplox/laravel-shield:^X.Y` in the consumer.

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
│   └── AppServiceProvider.php # Routes, morph map, Shield config, event listeners
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

Configured in `AppServiceProvider::register()` via the `Shield` singleton (central entry point for the laravel-shield package):

```php
Shield::configure($this->app, new Shield(
    tokenModel: Token::class,
    userModel: User::class,
    guards: ['session'],
    statefulDomains: ['localhost', '...', 'play.ddev.site'],
    prefix: 'dpl_',
    validateUser: fn (User $user): bool => $user->verified_at !== null,
));
```

`Shield` holds all configuration, the `boot()` method (guard + middleware + cookies + CSRF route registration), token-prefix methods (`decorateToken`, `extractRandom`), and three extension callbacks (`extractToken`, `validateToken`, `validateUser` — all non-nullable with sensible defaults). `ShieldServiceProvider` is a thin delegate that calls `Shield::boot()` and loads migrations.

**DynamicGuard flow:** Tries each configured guard (session) first. If none authenticate, falls back to bearer token via `AuthenticateToken`. Tokens are plain random strings (48 chars for bearer, 60 chars for remember). Stored as SHA256 hash, looked up via `WHERE token = hash(secret)`. Checks `expires_at`, updates `last_used_at` with debounce, dispatches `TokenAuthenticated` event.

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

- Each feature test file declares `uses(RefreshDatabase::class)` at the top (no global `uses()` in `Pest.php`)
- Session auth: `actingAs($user, 'dynamic')` (the `auth:dynamic` guard, not `web`)
- Token auth: `$this->withToken($token->plain, 'Bearer')` — `$token->plain` is set during creation, not retrievable later
- `tests/Unit/` is reserved for architecture tests and isolated logic; everything else goes in `tests/Feature/`

## Git

- Do **not** add `Co-Authored-By` lines to commits

## Code Conventions

- **ULID primary keys** on all models (`HasUlids` trait)
- **Architecture tests** in `tests/Unit/ArchitectureTest.php` enforce `php`, `security`, and `laravel` presets (covers `strict_types`, strict comparisons, `mb_*`, `DateTimeImmutable`)
- **Class element ordering** (enforced by Pint): use_trait → case → constants → properties → construct → destruct → magic → phpunit → abstract methods → public static → public → protected static → protected → private static → private

## Known Issues

- **PHPStan:** ~28 pre-existing errors unrelated to new work
- **PHP 8.4 trait conflicts:** Trait properties conflict with `final` class redefinitions — use getters with `??` fallback instead

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- rector/rector (RECTOR) - v2
- eslint (ESLINT) - v9
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `pnpm run build`, `pnpm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `pnpm run build` or ask the user to run `pnpm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
