# 10. Extension Callbacks

**Status:** Accepted

## Context

Authentication packages need extension points for custom logic: extracting tokens from non-standard locations, validating tokens against custom criteria, and validating users beyond standard checks. Sanctum uses static methods on the `Sanctum` class (e.g., `Sanctum::authenticateAccessTokensUsing()`), which introduce mutable static state.

## Decision

Three extension points are implemented as typed closures on the `Shield` singleton:

```php
/** @var Closure(Request): ?string */
public readonly Closure $extractToken;

/** @var Closure(IsAuthToken, Request): bool */
public readonly Closure $validateToken;

/** @var Closure(Authenticatable): bool */
public readonly Closure $validateUser;
```

Constructor parameters are nullable with defaults applied in the constructor body:

```php
public function __construct(
    // ...
    ?Closure $extractToken = null,
    ?Closure $validateToken = null,
    ?Closure $validateUser = null,
) {
    $this->extractToken = $extractToken ?? static fn (Request $request): ?string => $request->bearerToken();
    $this->validateToken = $validateToken ?? static fn (IsAuthToken $token, Request $request): bool => true;
    $this->validateUser = $validateUser ?? static fn (Authenticatable $user): bool => true;
}
```

## Rationale

- **Non-nullable properties** — The properties are always set, eliminating null-checks in `AuthenticateToken`, `DynamicGuard`, and `Login`. Every consumer can call `($this->shield->validateUser)($user)` without checking for null.
- **Type-safe** — PHPDoc annotations document the exact closure signatures. IDEs provide autocompletion and type checking.
- **Scoped** — Callbacks are scoped to the Shield instance, not global static state. No risk of leaking between test cases.
- **Sensible defaults** — `extractToken` defaults to `bearerToken()`, `validateToken` defaults to `true` (no-op), `validateUser` defaults to `true`. Most applications only need to customize `validateUser`.
- **Composable** — Since the default is not automatically included, consumers who override `extractToken` can compose the fallback themselves if needed (e.g., try custom header, fall back to `bearerToken()`).

## Alternatives Considered

- **Static methods** (Sanctum's approach) — Mutable static state, leaks between tests, hard to type-hint precisely.
- **Event listeners** — Too indirect for synchronous validation. Events are fire-and-forget by convention; using them for validation that returns a boolean is an anti-pattern.
- **Middleware** — Appropriate for request-level concerns but too coarse for token/user validation that runs inside the guard.
- **Strategy pattern classes** — More formal but heavier. Closures are sufficient for single-method extension points and don't require defining interfaces and classes.
