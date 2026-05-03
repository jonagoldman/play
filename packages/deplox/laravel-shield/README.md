# laravel-shield

Token-based authentication for Laravel with session-first dynamic guard, automatic token hashing, configurable extension points, and optional polymorphic token support.

## Requirements

- PHP 8.4+
- Laravel 13+

## Installation

```bash
composer require deplox/laravel-shield
```

The package uses Laravel's auto-discovery, so no manual provider registration is needed.

## Configuration

Configure the package in your `AppServiceProvider::register()` method via the `Shield` singleton:

```php
use Deplox\Shield\Shield;

public function register(): void
{
    Shield::configure($this->app, new Shield(
        tokenModel: \App\Models\Token::class,
        userModel: \App\Models\User::class,
    ));
}
```

### Shield Parameters

| Parameter                | Type           | Default               | Description                                                                 |
| ------------------------ | -------------- | --------------------- | --------------------------------------------------------------------------- |
| `tokenModel`             | `class-string` | *(required)*          | Your token model class                                                      |
| `userModel`              | `class-string` | *(required)*          | Your authenticatable user model class                                       |
| `guards`                 | `list<string>` | `['session']`         | Auth guards to try before falling back to bearer token                      |
| `statefulDomains`        | `list<string>` | `[]`                  | Domains allowed to use session cookie authentication                        |
| `prefix`                 | `string`       | `''`                  | Token prefix for secret scanning (e.g. `'dpl_'`)                            |
| `defaultTokenExpiration` | `?int`         | `2592000` (30 days)   | Default token lifetime in seconds. `null` = no default, `0` = no expiration |
| `pruneDays`              | `int`          | `30`                  | Days to keep expired tokens before pruning                                  |
| `lastUsedAtDebounce`     | `int`          | `300`                 | Seconds between `last_used_at` writes                                       |
| `maxTokensPerUser`       | `?int`         | `null`                | Max tokens per user (`null` = unlimited)                                    |
| `onTokenLimit`           | `TokenLimitBehavior` | `Reject`        | Behaviour when the token cap is reached (`Reject` or `PruneOldest`)         |
| `revokeOnPasswordChange` | `RevokeOnPasswordChange` | `Bearer`    | Which tokens to revoke on password reset (`All`, `Bearer`, or `None`)        |
| `secureCookies`          | `bool`         | `true`                | Enable secure session cookie settings                                       |
| `csrfCookiePath`         | `string`       | `'/auth/csrf-cookie'` | CSRF cookie endpoint path                                                   |
| `maxLoginAttempts`       | `int`          | `5`                   | Max failed `Login` attempts per key before throttling                       |
| `loginDecaySeconds`      | `int`          | `60`                  | Throttle window for failed `Login` attempts                                 |
| `middlewares`            | `array`        | *(see below)*         | Overridable middleware classes                                              |
| `extractToken`           | `?Closure`     | `bearerToken()`       | Custom token extraction from request                                        |
| `validateToken`          | `?Closure`     | `fn () => true`       | Custom token validation callback                                            |
| `validateUser`           | `?Closure`     | `fn () => true`       | Custom user validation callback                                             |

Full example with all options:

```php
Shield::configure($this->app, new Shield(
    tokenModel: Token::class,
    userModel: User::class,
    guards: ['session'],
    statefulDomains: ['localhost', 'app.example.com'],
    prefix: 'dpl_',
    defaultTokenExpiration: 60 * 60 * 24 * 30, // 30 days
    pruneDays: 30,
    lastUsedAtDebounce: 300,
    secureCookies: true,
    csrfCookiePath: '/auth/csrf-cookie',
    validateUser: fn (User $user): bool => $user->verified_at !== null,
));
```

## Database Setup

Publish the default migration:

```bash
php artisan vendor:publish --tag=laravel-shield-migrations
```

This creates a `tokens` table:

```
id          - ULID primary key
user_id     - Foreign key to users table (indexed, constrained)
name        - Nullable string (human-readable label)
type        - String (bearer or remember)
token       - String(64), unique (SHA256 hash of the raw token)
expires_at  - Nullable timestamp (indexed)
last_used_at - Nullable timestamp
created_at  - Timestamp
updated_at  - Timestamp
```

Then run the migration:

```bash
php artisan migrate
```

For polymorphic tokens, see [Polymorphic Tokens](#polymorphic-tokens).

## Models

### Token Model

Create a Token model that implements the `IsAuthToken` contract and uses the `IsAuthToken` trait:

```php
use Illuminate\Database\Eloquent\Model;
use Deplox\Shield\Concerns\IsAuthToken as IsAuthTokenConcern;
use Deplox\Shield\Contracts\IsAuthToken;

final class Token extends Model implements IsAuthToken
{
    use IsAuthTokenConcern;
}
```

The trait provides:

- `HasUlids` for ULID primary keys
- `HasExpiration` for expiry checking
- `MassPrunable` for scheduled cleanup
- Auto-hashing `token` attribute (SHA256 on write)
- `findByToken()` static method for hash-based lookup
- `touchLastUsedAt()` with configurable debounce
- `owner()` relationship to the user model
- `plain` accessor (populated only after creation or `setPlain()`)
- `prunable()` query for expired tokens older than `pruneDays`
- Casts: `type` to `TokenType`, `expires_at` and `last_used_at` to `immutable_datetime`

### User Model

Your user model must implement the `HasTokens` contract and use the `HasTokens` trait:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Deplox\Shield\Concerns\HasTokens as HasTokensConcern;
use Deplox\Shield\Contracts\HasTokens;

final class User extends Authenticatable implements HasTokens
{
    use HasTokensConcern;
}
```

This provides:

- `tokens()` — `HasMany` relationship to all tokens
- `token()` — `HasOne` relationship to the latest token
- `createToken()` — Create a new token (see [Token Management](#token-management))

## Authentication Flow

The package registers a `dynamic` auth guard that provides a session-first, bearer-fallback authentication strategy.

### DynamicGuard

When a request arrives with `auth:dynamic` middleware:

1. **Session check** — Tries each guard in `Shield::$guards` (default: `['session']`). If a session-authenticated user is found, sets `$user->token = null` and returns the user.
2. **Bearer fallback** — If no session user, extracts the token via `Shield::$extractToken` (default: `$request->bearerToken()`) and runs `AuthenticateToken`.
3. **Events** — Dispatches Laravel's `Login` event for both paths. Bearer auth additionally dispatches `TokenAuthenticated`.

### Guard Registration

The guard is registered automatically. Use it in routes:

```php
Route::middleware('auth:dynamic')->group(function () {
    // Both session-authenticated and token-authenticated users can access these routes
});
```

### How to Tell Session vs Bearer Apart

```php
$user = $request->user();

if ($user->token === null) {
    // Session-authenticated
} else {
    // Bearer token-authenticated
    // $user->token is the IsAuthToken model instance
}
```

## Token Management

### Creating Tokens

```php
use Deplox\Shield\Enums\TokenType;

// Basic bearer token (30-day default expiration)
$token = $user->createToken();

// Named token
$token = $user->createToken(name: 'api-key');

// Remember token (longer random string)
$token = $user->createToken(TokenType::Remember);

// Custom expiration
$token = $user->createToken(expiresAt: now()->addYear());

// No expiration (pass a zero-second duration or configure defaultTokenExpiration: 0)
$token = $user->createToken(expiresAt: now()->addSeconds(0));
```

The `$token->plain` property contains the full decorated token (prefix + random + checksum). This is the value to return to the client. It is only available immediately after creation.

### Token Types

The `TokenType` enum defines two types:

| Type       | Value        | Random Length |
| ---------- | ------------ | ------------- |
| `Bearer`   | `'bearer'`   | 48 characters |
| `Remember` | `'remember'` | 60 characters |

### Token Prefix & Decoration

When a `prefix` is configured, tokens are decorated as:

```
{prefix}{random}{crc32b(random)}
```

For example, with prefix `dpl_`:
- Raw random: `abc123...` (48 chars)
- CRC32B checksum: `a1b2c3d4` (8 chars)
- Decorated: `dpl_abc123...a1b2c3d4`

The prefix enables automated secret scanning (e.g., GitHub secret scanning). The CRC32B checksum catches truncation or corruption before hitting the database.

### Token Storage

Tokens are stored as SHA256 hashes. The `token` attribute mutator automatically hashes on write, making it impossible to accidentally persist plaintext. Lookup uses `WHERE token = SHA256(secret)` with a unique index.

### Revoking Tokens

```php
// Delete a specific token
$token->delete();

// Delete all tokens for a user
$user->tokens()->delete();
```

## Login & Logout

### Login Action

The `Login` action authenticates via credentials with two modes:

```php
use Deplox\Shield\Actions\Login;

$login = app(Login::class);

// Stateful (session-based) — creates a session
$user = $login($credentials, stateful: true);

// Stateless (API/token flow) — validates without session side effects
$user = $login($credentials, stateful: false);
```

Parameters:
- `$credentials` — `['email' => '...', 'password' => '...']`
- `$stateful` — `true` for session auth, `false` for token flow
- `$field` — Field name for validation error messages (default: `'email'`)

Throws `ValidationException` on failure. The `validateUser` callback is applied in both modes.

### Logout Action

The `Logout` action automatically handles both auth methods:

```php
use Deplox\Shield\Actions\Logout;

$logout = app(Logout::class);
$logout($request);
```

- **Bearer auth** (`$user->token` is set) — Deletes the token
- **Session auth** (`$user->token` is null) — Invalidates session, regenerates CSRF token

## SPA / Stateful Authentication

For single-page applications, configure `statefulDomains` to enable session cookie authentication for API routes:

```php
Shield::configure($this->app, new Shield(
    // ...
    statefulDomains: ['localhost', 'spa.example.com'],
));
```

### StatefulFrontend Middleware

The `StatefulFrontend` middleware is automatically prepended to the middleware priority list. It checks if the `Referer` or `Origin` header matches a configured stateful domain. For matching requests, it applies the session middleware pipeline:

1. `EncryptCookies`
2. `AddQueuedCookiesToResponse`
3. `StartSession`
4. `VerifyCsrfToken`
5. `AuthenticateSession`

### CSRF Cookie Endpoint

A CSRF cookie endpoint is automatically registered at the configured path (default: `GET /auth/csrf-cookie`). Your SPA should call this before making authenticated requests to obtain a CSRF token.

## Extension Points

### extractToken

Customize how the bearer token is extracted from the request:

```php
Shield::configure($this->app, new Shield(
    // ...
    extractToken: function (Request $request): ?string {
        // Try custom header first, fall back to standard bearer
        return $request->header('X-Api-Key') ?? $request->bearerToken();
    },
));
```

### validateToken

Add custom validation logic for tokens (runs after expiry and lookup checks):

```php
Shield::configure($this->app, new Shield(
    // ...
    validateToken: function (IsAuthToken $token, Request $request): bool {
        // Example: IP allowlisting
        return $token->allowed_ip === $request->ip();
    },
));
```

### validateUser

Add custom validation logic for the authenticated user:

```php
Shield::configure($this->app, new Shield(
    // ...
    validateUser: fn (User $user): bool => $user->verified_at !== null,
));
```

This callback is applied in both the `DynamicGuard` (bearer path) and the `Login` action (stateful path via `attemptWhen`).

## Middleware Configuration

Override specific middleware in the stateful pipeline by passing the `middlewares` parameter:

```php
Shield::configure($this->app, new Shield(
    // ...
    middlewares: [
        'encrypt_cookies' => \App\Http\Middleware\CustomEncryptCookies::class,
        'validate_csrf_token' => null, // Remove CSRF validation
        'authenticate_session' => \App\Http\Middleware\CustomAuthSession::class,
    ],
));
```

Set a key to `null` to remove that middleware from the pipeline entirely.

Default middleware:

| Key                    | Default Class                                           |
| ---------------------- | ------------------------------------------------------- |
| `encrypt_cookies`      | `Illuminate\Cookie\Middleware\EncryptCookies`           |
| `validate_csrf_token`  | `Illuminate\Foundation\Http\Middleware\PreventRequestForgery` |
| `authenticate_session` | `Deplox\Shield\Middlewares\AuthenticateSession`         |

## Token Pruning

The token model uses Laravel's `MassPrunable` trait. Expired tokens older than `pruneDays` are automatically eligible for pruning.

Schedule the prune command in your application:

```php
// routes/console.php or bootstrap/app.php
Schedule::command('model:prune', ['--model' => \App\Models\Token::class])->daily();
```

Additionally, expired tokens are proactively deleted during authentication attempts, keeping the table clean between scheduled prune runs.

## API Resource

The package includes `TokenResource` for API responses:

```php
use Deplox\Shield\Resources\TokenResource;

return new TokenResource($token);
```

Fields:

| Field          | Description                                           |
| -------------- | ----------------------------------------------------- |
| `id`           | ULID                                                  |
| `user_id`      | Owner's ID                                            |
| `name`         | Human-readable label                                  |
| `type`         | `bearer` or `remember`                                |
| `token`        | Plain token (only present immediately after creation) |
| `expired`      | Boolean                                               |
| `expires_at`   | ISO 8601 Zulu string                                  |
| `last_used_at` | ISO 8601 Zulu string                                  |
| `created_at`   | ISO 8601 Zulu string                                  |
| `updated_at`   | ISO 8601 Zulu string                                  |

## Polymorphic Tokens

By default, tokens use a direct foreign key (`user_id`) to the users table. For applications where multiple model types need tokens, opt in to polymorphic tokens.

### 1. Publish the Polymorphic Migration

```bash
php artisan vendor:publish --tag=laravel-shield-morph-migrations
```

This creates a `tokens` table with `owner_id` and `owner_type` columns instead of `user_id`.

### 2. Swap the User Model Trait

Replace `HasTokens` with `HasMorphTokens` on your user model (and any other authenticatable models):

```php
use Deplox\Shield\Concerns\HasMorphTokens as HasMorphTokensConcern;
use Deplox\Shield\Contracts\HasMorphTokens;

final class User extends Authenticatable implements HasMorphTokens
{
    use HasMorphTokensConcern;
}
```

### 3. Override owner() on the Token Model

The token model's `owner()` method returns `BelongsTo` by default. For polymorphic tokens, override it to return `MorphTo`:

```php
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class Token extends Model implements IsAuthToken
{
    use IsAuthTokenConcern;

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
```

This is covariant (`MorphTo extends BelongsTo`), so it satisfies the `IsAuthToken` contract.

### Contract Hierarchy

Both modes share a common `OwnsTokens` contract that declares `createToken()`:

```
OwnsTokens (marker, declares createToken)
├── HasTokens (extends OwnsTokens — HasMany/HasOne, direct FK)
└── HasMorphTokens (extends OwnsTokens — MorphMany/MorphOne)
```

Shield validates that `userModel` implements `OwnsTokens`, so both modes pass validation.

## Events

| Event                                     | When                                          | Payload                                              |
| ----------------------------------------- | --------------------------------------------- | ---------------------------------------------------- |
| `Illuminate\Auth\Events\Attempting`       | Before token lookup                           | `guard: 'dynamic'`, `credentials: ['token' => ...]`  |
| `Deplox\Shield\Events\TokenAuthenticated` | After successful bearer auth                  | `token: Model&IsAuthToken`                           |
| `Deplox\Shield\Events\TokenRevoked`       | After a token is intentionally revoked        | `token`, `user`, `reason: TokenRevocationReason`     |
| `Deplox\Shield\Events\FailedLogin`        | After a failed `Login` action attempt         | `field`, `identifier`, `ip` — never the password     |
| `Illuminate\Auth\Events\Login`            | After any successful auth (session or bearer) | `guard: 'dynamic'`, `user`, `remember: false`        |
| `Illuminate\Auth\Events\Failed`           | After failed auth attempt                     | `guard: 'dynamic'`, `user` (if found), `credentials` |

## Password Reset & Email Verification

Shield includes opt-in actions, controllers, and route helpers for password reset and email verification. Wire up the controllers via `Shield::registerPasswordResetRoutes()` / `Shield::registerEmailVerificationRoutes()`, or call the actions directly from your own controllers.

### Route helpers

```php
use Deplox\Shield\Shield;

// In AppServiceProvider::boot(), after configuring Shield:
Shield::registerPasswordResetRoutes();          // POST /password/email, POST /password/reset
Shield::registerEmailVerificationRoutes();      // POST /email/verification-notification, GET /email/verify/{id}/{hash}

// Customise prefix and middleware groups:
Shield::registerPasswordResetRoutes(prefix: 'auth/password', middleware: ['api']);
Shield::registerEmailVerificationRoutes(prefix: 'auth/email', middleware: ['api']);
```

The verify route is protected by Laravel's signed-URL middleware automatically. The "send verification" route requires `auth:dynamic` so only authenticated users can re-trigger it.

### Actions

| Action                  | Returns | Notes                                                                                         |
| ----------------------- | ------- | --------------------------------------------------------------------------------------------- |
| `SendPasswordReset`     | `string` (broker status) | Validates `email`, delegates to the password broker. Throws `ValidationException` on bad input. |
| `ResetPassword`         | `string` (broker status) | Validates `email`, `password` (`confirmed`, `min:8`), `token`. Dispatches `PasswordReset`.   |
| `SendEmailVerification` | `bool`   | Rate-limited per user-id + IP (default: 6 attempts / hour). Returns `false` if already verified. |
| `VerifyEmail`           | `bool`   | Validates the SHA1 hash matches the user's email. Dispatches `Verified` on success.            |

```php
use Deplox\Shield\Actions\SendPasswordReset;

$status = app(SendPasswordReset::class)(['email' => $request->email]);

return match ($status) {
    'passwords.sent' => response()->json(['ok' => true]),
    default          => abort(400, $status),
};
```

The default controllers (`SendPasswordResetController`, `ResetPasswordController`, `SendEmailVerificationController`, `VerifyEmailController`) are thin wrappers around these actions and are wired up by the route helpers above. Use them as references for building your own.

## Additional middleware

| Middleware             | Purpose                                                                                          |
| ---------------------- | ------------------------------------------------------------------------------------------------ |
| `DenyAuthenticated`    | Inverse of `auth` — rejects already-authenticated requests with `403`. Useful for routes like `POST /register` that should only accept guests. |
| `ResolveCurrentUser`   | Substitutes the route parameter `'me'` with the authenticated user. Lets `/users/{user}` accept `/users/me` for "current user" semantics. |

```php
Route::middleware(['auth:dynamic', ResolveCurrentUser::class])
    ->get('/users/{user}', UserController::class);

// /users/me  → resolves {user} to $request->user()
// /users/abc → resolves {user} via the route binding as usual
```

## Token policy

`Deplox\Shield\Policies\TokenPolicy` ships ready to gate token CRUD by ownership. Register it in your `AuthServiceProvider`:

```php
protected $policies = [
    Token::class => \Deplox\Shield\Policies\TokenPolicy::class,
];
```

Then use `$this->authorize('delete', $token)` in controllers. The policy methods (`view`, `update`, `delete`) check `$user->id === $token->getOwnerKey()`.

## Listener: `RevokeTokensOnPasswordReset`

When `revokeOnPasswordChange` is `Bearer` (default) or `All`, Shield registers a listener for Laravel's `PasswordReset` event that revokes the user's tokens (and dispatches `TokenRevoked` events with reason `PasswordReset`). Set `revokeOnPasswordChange: RevokeOnPasswordChange::None` on the `Shield` constructor if you want to handle revocation yourself.

## Enums

| Enum                        | Cases                                                  | Used by                                          |
| --------------------------- | ------------------------------------------------------ | ------------------------------------------------ |
| `TokenType`                 | `Bearer`, `Remember`                                   | Token model `type` cast, length resolution       |
| `TokenLimitBehavior`        | `Reject`, `PruneOldest`                                | `Shield::$onTokenLimit`                          |
| `RevokeOnPasswordChange`    | `All`, `Bearer`, `None`                                | `Shield::$revokeOnPasswordChange`                |
| `TokenRevocationReason`     | `Logout`, `LogoutAll`, `PasswordReset`                 | `TokenRevoked` event payload                     |

## Exceptions

| Exception                       | Thrown when                                                                                  |
| ------------------------------- | -------------------------------------------------------------------------------------------- |
| `TokenLimitExceededException`   | A user attempts to create a token while at the `maxTokensPerUser` cap, with `onTokenLimit: Reject`. |

## Testing

See [docs/testing.md](docs/testing.md) for testing helpers and patterns.

## Architecture Decisions

See [docs/decisions/](docs/decisions/) for Architecture Decision Records (ADRs) explaining the design rationale.

## Comparison with Sanctum

See [docs/comparison.md](docs/comparison.md) for a detailed comparative analysis against the Sanctum fork.
