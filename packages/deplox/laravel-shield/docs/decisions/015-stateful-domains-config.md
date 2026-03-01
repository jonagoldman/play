# 15. Environment-Driven Stateful Domains

**Status:** Accepted

## Context

Shield's `statefulDomains` were hardcoded in the consuming app's `AppServiceProvider` — including environment-specific domains like `play.ddev.site`. Every new project or production deploy required editing PHP code. Sanctum solves this with a publishable config file, a `SANCTUM_STATEFUL_DOMAINS` env var, and auto-derivation of the host from `APP_URL`.

Shield should follow the same pattern while preserving its constructor-centric design.

## Decision

### Nullable-param pattern

The `statefulDomains` constructor parameter defaults to `null` instead of an empty array. When `null`, `Shield::statefulDomains()` resolves from `config('shield.stateful')`. When explicitly passed, the array is used directly (backward compatible).

This follows the same nullable-param pattern already established for Shield's extension callbacks (`extractToken`, `validateToken`, `validateUser`).

```php
// Config-driven (default) — reads from config/shield.php + SHIELD_STATEFUL_DOMAINS env
new Shield(tokenModel: Token::class, userModel: User::class);

// Explicit override — ignores config entirely
new Shield(tokenModel: Token::class, userModel: User::class, statefulDomains: ['example.com']);
```

### Publishable config

A `config/shield.php` file is added to the package with two keys:

- `stateful` — reads from `SHIELD_STATEFUL_DOMAINS` env var, defaults to common dev domains, auto-appends `APP_URL` host via `Shield::currentApplicationUrlWithPort()`
- `stateful_subdomains` — boolean flag, default `false`; when enabled, subdomain patterns (`*.domain/*`) are also matched

The config is merged via `mergeConfigFrom()` and publishable via `laravel-shield-config` tag.

### Subdomain matching

When `stateful_subdomains` is enabled, `StatefulFrontend::fromFrontend()` generates both `domain/*` and `*.domain/*` patterns for each configured domain using `flatMap`.

## Rationale

- **Environment portability** — Stateful domains change per environment (local, staging, production). The env var eliminates PHP code changes for domain configuration.
- **APP_URL auto-derivation** — `currentApplicationUrlWithPort()` automatically includes the application's host, matching Sanctum's behavior. No need to manually duplicate the domain.
- **Backward compatible** — Existing code that passes `statefulDomains` explicitly continues to work unchanged. The nullable default is opt-in.
- **Subdomain flexibility** — The `stateful_subdomains` flag handles multi-tenant or subdomain-based SPAs without manual wildcard configuration.

## Alternatives Considered

- **Always require explicit domains** (previous approach) — Requires PHP code changes per environment. Not portable.
- **Config-only (no constructor override)** — Loses the ability to override in tests or specialized setups without touching config.
- **Wildcard patterns in the domain list** — More flexible but shifts pattern complexity to the consumer. The boolean flag covers the most common subdomain case.
