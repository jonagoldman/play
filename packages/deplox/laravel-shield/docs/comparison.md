# Comparative Analysis: laravel-shield vs [Sanctum Fork (4.x-opinionated)](https://github.com/deplox/laravel-sanctum/tree/4.x-opinionated)

> For deeper rationale behind each design decision, see the [Architecture Decision Records](decisions/).

## Context

The [Sanctum fork](https://github.com/deplox/laravel-sanctum/tree/4.x-opinionated) already makes several opinionated departures from upstream Sanctum:

- Removed the entire abilities/scopes system (no `$abilities` param, no `tokenCan()`)
- Removed `TransientToken` class
- Refactored Guard to accept `UserProvider` via `__invoke()` parameter
- Added `AuthenticateSession` middleware with `hashPasswordForCookie` compatibility
- Changed guard config from single string to array (`['web']`)
- Stricter type hints throughout

Both packages share these same opinionated decisions, so the **delta is smaller** than laravel-shield vs upstream Sanctum.

---

## Improvements in laravel-shield

### 1. IsAuthToken contract + trait vs concrete PersonalAccessToken

> ADR: [002 — Contract + Trait Pattern](decisions/002-contract-trait-pattern.md)

**Clear improvement.** Sanctum provides a concrete model and needs `Sanctum::usePersonalAccessTokenModel()` as an escape hatch.
laravel-shield defines a `Contracts\IsAuthToken` interface and a `Concerns\IsAuthToken` trait — the consuming app owns its model and mixes in behavior. More composable, no workaround needed.

### 2. Auto-hashing token attribute mutator

> ADR: [004 — Token Hashing](decisions/004-token-hashing.md)

**Clear improvement.** Sanctum hashes in `createToken()` -- the hashing is a caller responsibility.
The `token()` Attribute mutator hashes on write regardless of call site. Impossible to accidentally persist plaintext.

### 3. Debounced touchLastUsedAt() (configurable, default 300s)

> ADR: [011 — Debounced last_used_at](decisions/011-debounced-last-used.md)

**Clear improvement.** Sanctum fork writes on every request or disables entirely (boolean toggle).
The configurable debounce cuts write volume dramatically with negligible accuracy loss.

### 4. MassPrunable vs PruneExpired command

**Improvement.** Uses Laravel's built-in `model:prune` infrastructure. Less code to maintain, integrates with existing scheduling.
Sanctum fork's PruneExpired has dual-path pruning logic (expires_at + created_at based on global expiration config) that's more complex
but also tied to the now-removed global expiration concept.

### 5. AuthenticateToken as standalone action class

> ADR: [003 — Dynamic Guard](decisions/003-dynamic-guard.md)

**Improvement.** Extracting token validation from the Guard into an invokable action is cleaner separation.
Independently testable, reusable, and the Guard stays focused on orchestration.

### 6. Shield as central entry point

> ADR: [001 — Shield over Config](decisions/001-shield-over-config.md)

**Improvement.** Type-safe singleton that holds all configuration, boot logic, and token-prefix methods. Validates at construction time (catches misconfiguration early), easier to mock in tests.
Replaces scattered `config()` calls and Sanctum's static properties.

### 7. TokenType enum (Bearer vs Remember)

> ADR: [012 — Token Types](decisions/012-token-types.md)

**Improvement.** Adds a semantic dimension Sanctum lacks. Type-specific generation logic and centralized `generate()` method are clean.
Enables different token behaviors per type.

### 8. Null token for session-authenticated users

> ADR: [013 — Null Token for Session Auth](decisions/013-null-token-session.md)

**Improvement.** Session-authenticated users get `$user->token = null`, bearer-authenticated users get the actual token model instance.
This provides a unified `$user->token` interface without a marker class — `null` vs token instance is the discriminator.
Simpler than Sanctum's `TransientToken` wrapper and avoids an extra class.

### 9. ULIDs over auto-incrementing IDs

> ADR: [005 — ULIDs over Auto-increment](decisions/005-ulids-over-autoincrement.md)

**Improvement.** Non-sequential, no information leakage about total count or creation order.
Good default for security-sensitive entities like tokens.

### 10. Login event dispatching

**Lateral improvement.** laravel-shield dispatches Laravel's standard `Login` event for both session and bearer auth,
integrating tightly with the auth event system. Sanctum only fires `TokenAuthenticated`.
Whether this is better depends on whether listeners expect `Login` for API tokens.

### 11. Naming

**Minor improvement.** `StatefulFrontend` vs `EnsureFrontendRequestsAreStateful`, `DynamicGuard` vs `Guard`. Shorter, equally descriptive.

### 12. Hash-based token lookup (no pipe-delimited format)

> ADR: [006 — Hash Lookup](decisions/006-hash-lookup.md)

**Clear improvement.** ULIDs make the `id|token` pipe-delimited format unnecessary.
Sanctum's format was a workaround for auto-incrementing integer PKs where a full-table scan would be expensive.
With ULIDs, a hash-based lookup with a DB index on the token column is the correct approach,
no information leakage from the ID prefix, simpler token format, and equivalent performance.

### 13. Proactive expired token cleanup on auth

> ADR: [011 — Debounced last_used_at](decisions/011-debounced-last-used.md) *(covers proactive cleanup in context)*

**Clear improvement.** Deleting expired tokens during auth attempts complements the MassPrunable scheduled pruning.
Expired tokens are by definition invalid -- deleting them immediately reduces table size and speeds up future lookups.
The `pruneDays` config handles bulk cleanup of tokens that never get hit again.

### 14. Container-bound config (no static state)

> ADR: [001 — Shield over Config](decisions/001-shield-over-config.md)

**Clear improvement.** `Shield::configure()` accepts the `Application` instance and binds `Shield` as a singleton directly into the container.
No static mutable state, no `$pendingConfig` bridging — standard Laravel DI from the start.

### 15. Configurable middleware stack

> ADR: [014 — Configurable Middleware](decisions/014-configurable-middleware.md)

**Clear improvement.** `Shield::$middlewares` accepts overrides for `encrypt_cookies`, `validate_csrf_token`, and `authenticate_session`.
Set a key to `null` to remove it. Sanctum's fork hardcodes the middleware list with no override mechanism.

### 16. Extension callbacks via Shield closures

> ADR: [010 — Extension Callbacks](decisions/010-extension-callbacks.md)

**Clear improvement.** Three extension points matching Sanctum's capabilities, implemented as typed closures on the `Shield` singleton:

- `$extractToken` — custom token extraction (e.g., from query param, custom header). Non-nullable with a default that returns `bearerToken()`. Override to fully own extraction — compose the fallback yourself if needed.
- `$validateToken` — custom token validation (e.g., IP allowlisting). Non-nullable with a default that returns `true` (no-op). Receives the token model and request, runs after standard checks but before `$validateUser`.
- `ActingAsToken` trait — testing convenience that creates a real token and sets the `Authorization` header.

Cleaner than Sanctum's static method approach: closures are type-safe, non-nullable with sensible defaults, and scoped to the config instance.

### 17. Environment-driven stateful domains

> ADR: [015 — Stateful Domains Config](decisions/015-stateful-domains-config.md)

**Improvement.** Sanctum uses a publishable config with `SANCTUM_STATEFUL_DOMAINS` env var and auto-derives from `APP_URL`.
laravel-shield now follows the same pattern: `config/shield.php` with `SHIELD_STATEFUL_DOMAINS` env var and `Shield::currentApplicationUrlWithPort()`.
The nullable-param pattern also allows explicit overrides via the constructor when needed (e.g., tests), which Sanctum cannot do.
Optional `stateful_subdomains` flag adds `*.domain/*` patterns for multi-tenant SPAs.

---

## Regressions in laravel-shield

### ~~1. HasMany instead of MorphMany (polymorphic)~~ — Resolved

> ADR: [008 — Polymorphic Tokens](decisions/008-polymorphic-tokens.md)

**No longer a regression.** laravel-shield now supports both modes via alternative traits:

- **Default (direct FK):** `HasTokens` trait with `HasMany`/`HasOne` — simpler schema, database-level referential integrity, better performance. Zero changes from before.
- **Opt-in polymorphic:** `HasMorphTokens` trait with `MorphMany`/`MorphOne` — swap one trait on the owner model, override `owner()` on the token model to return `MorphTo`, and publish the polymorphic migration via `laravel-shield-morph-migrations`.

This follows the Laravel pattern of alternative traits (like `HasUuids` vs `HasUlids`). The token model's `owner()` method returns `BelongsTo` by default; since `MorphTo extends BelongsTo`, consumers can covariantly override it without a separate trait. On the owner side, `MorphMany` does not extend `HasMany`, so a separate `HasMorphTokens` trait is provided.

Both traits share a common `OwnsTokens` contract and `CreatesTokens` trait, so `createToken()` works identically in both modes.

---

## Summary

| Item                            | Category               |
| ------------------------------- | ---------------------- |
| IsAuthToken contract + trait    | Clear improvement      |
| Auto-hash mutator               | Clear improvement      |
| Debounced writes                | Clear improvement      |
| MassPrunable                    | Clear improvement      |
| Action class                    | Clear improvement      |
| Shield entry point              | Clear improvement      |
| TokenType enum                  | Clear improvement      |
| Null token (no TransientToken)  | Clear improvement      |
| ULIDs                           | Clear improvement      |
| Hash-based lookup               | Clear improvement      |
| Proactive expired token cleanup | Clear improvement      |
| Container-bound config          | Clear improvement      |
| Configurable middleware stack   | Clear improvement      |
| Extension callbacks             | Clear improvement      |
| Naming                          | Minor improvement      |
| Login event integration         | Minor improvement      |
| Polymorphic tokens (opt-in)     | Clear improvement      |
| Env-driven stateful domains     | Improvement            |

**Bottom line:** The architectural foundation of laravel-shield is genuinely stronger.
DI over statics, contracts over concrete models, action classes, ULIDs, debounced writes, auto-hashing, built-in pruning.
These are real improvements. No regressions remain — polymorphic tokens are now supported as an opt-in alternative.
