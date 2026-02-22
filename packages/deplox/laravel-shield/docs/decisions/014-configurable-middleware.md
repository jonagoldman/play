# 14. Configurable Middleware

**Status:** Accepted

## Context

The `StatefulFrontend` middleware applies a pipeline of session-related middleware for requests from configured stateful domains. Some applications need to swap middleware implementations (e.g., custom cookie encryption) or remove specific middleware entirely (e.g., disabling CSRF for trusted SPA origins). Sanctum's fork hardcodes this middleware list with no override mechanism.

## Decision

`Shield::$middlewares` is an associative array with named keys and class-string or null values:

```php
public readonly array $middlewares = [
    'encrypt_cookies' => \Illuminate\Cookie\Middleware\EncryptCookies::class,
    'validate_csrf_token' => \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
    'authenticate_session' => Middlewares\AuthenticateSession::class,
],
```

`StatefulFrontend` reads from this array when building the pipeline. Setting a key to `null` removes that middleware entirely:

```php
Shield::configure($this->app, new Shield(
    // ...
    middlewares: [
        'validate_csrf_token' => null, // Disable CSRF for stateful requests
    ],
));
```

The `frontendMiddleware()` method filters out nulls and deduplicates:

```php
private function frontendMiddleware(): array
{
    return array_values(array_filter(array_unique([
        'encrypt_cookies' => $this->shield->middlewares['encrypt_cookies'],
        'response_cookies' => AddQueuedCookiesToResponse::class,
        'start_session' => StartSession::class,
        'validate_csrf_token' => $this->shield->middlewares['validate_csrf_token'],
        'authenticate_session' => $this->shield->middlewares['authenticate_session'],
    ])));
}
```

## Rationale

- **Granular control** — Override or remove individual middleware without affecting the rest of the pipeline.
- **Null-to-remove pattern** — `null` is a clear, unambiguous way to say "don't include this middleware." No boolean flags or special string values.
- **Named keys** — Keys describe the middleware's purpose, making the configuration self-documenting and merge-friendly.
- **No breaking changes** — Defaults match the previous hardcoded values. Existing applications work without changes.

## Alternatives Considered

- **Hardcoded middleware list** (Sanctum's approach) — No customization possible. Applications that need different middleware must fork or wrap the entire `StatefulFrontend` class.
- **Full pipeline replacement** — A single array that replaces the entire middleware stack. Loses the granularity of overriding individual middleware and requires the consumer to know the full default list.
- **Boolean flags per middleware** (`disableCsrf: true`) — Doesn't support swapping implementations, only toggling. Would need both a flag and a class override mechanism.
