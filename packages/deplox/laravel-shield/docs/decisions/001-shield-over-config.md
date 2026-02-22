# 1. Shield Over Config

**Status:** Accepted

## Context

Authentication packages need a central configuration object. Sanctum uses a combination of `config()` calls, static properties on the `Sanctum` class, and a `$pendingConfig` bridging mechanism. This scatters configuration across multiple access patterns and introduces mutable static state that persists between test cases.

## Decision

All configuration is centralized in `Shield`, a `final` class with a typed constructor. It is bound as a singleton via `Shield::configure($app, new Shield(...))` in `AppServiceProvider::register()`.

Shield holds:
- All configuration parameters (typed constructor properties)
- Boot logic (guard registration, middleware priority, secure cookies, CSRF route)
- Token-prefix methods (`decorateToken`, `extractRandom`)
- Extension callbacks (`extractToken`, `validateToken`, `validateUser`)

All internal classes receive `Shield` via constructor injection.

## Rationale

- **Type safety** — Constructor property promotion with typed parameters catches misconfiguration at construction time, not at runtime.
- **Validation** — `validateModels()` runs in the constructor, verifying that `tokenModel` implements `IsAuthToken` and `userModel` implements `OwnsTokens` before the application boots.
- **Standard DI** — Bound as a singleton in Laravel's container. No static state, no `config()` calls, no bridging mechanisms. Every consumer gets the same instance via dependency injection.
- **Testability** — Easy to mock or swap in tests without worrying about static state leaking between test cases.
- **Discoverability** — One constructor signature documents every configuration option with types and defaults.

## Alternatives Considered

- **Sanctum's approach** (`config()` + static properties) — Scatters config, requires `$pendingConfig` bridge, static state leaks between tests.
- **Dedicated config file** (`config/auth-shield.php`) — Adds a file but loses type safety and constructor validation. Common in packages but less precise.
- **Static methods on Shield** — Avoids the DI step but reintroduces static mutable state and complicates testing.
