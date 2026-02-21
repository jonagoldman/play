# Comparative Analysis: laravel-auth vs [Sanctum Fork (4.x-opinionated)](https://github.com/deplox/laravel-sanctum/tree/4.x-opinionated)

## Context

The [Sanctum fork](https://github.com/deplox/laravel-sanctum/tree/4.x-opinionated) already makes several opinionated departures from upstream Sanctum:

- Removed the entire abilities/scopes system (no `$abilities` param, no `tokenCan()`)
- Removed `TransientToken` class
- Refactored Guard to accept `UserProvider` via `__invoke()` parameter
- Added `AuthenticateSession` middleware with `hashPasswordForCookie` compatibility
- Changed guard config from single string to array (`['web']`)
- Stricter type hints throughout

Both packages share these same opinionated decisions, so the **delta is smaller** than laravel-auth vs upstream Sanctum.

---

## Improvements in laravel-auth

### 1. IsAuthToken contract + trait vs concrete PersonalAccessToken

**Clear improvement.** Sanctum provides a concrete model and needs `Sanctum::usePersonalAccessTokenModel()` as an escape hatch.
laravel-auth defines a `Contracts\IsAuthToken` interface and a `Concerns\IsAuthToken` trait — the consuming app owns its model and mixes in behavior. More composable, no workaround needed.

### 2. Auto-hashing token attribute mutator

**Clear improvement.** Sanctum hashes in `createToken()` -- the hashing is a caller responsibility.
The `token()` Attribute mutator hashes on write regardless of call site. Impossible to accidentally persist plaintext.

### 3. Debounced touchLastUsedAt() (configurable, default 300s)

**Clear improvement.** Sanctum fork writes on every request or disables entirely (boolean toggle).
The configurable debounce cuts write volume dramatically with negligible accuracy loss.

### 4. MassPrunable vs PruneExpired command

**Improvement.** Uses Laravel's built-in `model:prune` infrastructure. Less code to maintain, integrates with existing scheduling.
Sanctum fork's PruneExpired has dual-path pruning logic (expires_at + created_at based on global expiration config) that's more complex
but also tied to the now-removed global expiration concept.

### 5. AuthenticateToken as standalone action class

**Improvement.** Extracting token validation from the Guard into an invokable action is cleaner separation.
Independently testable, reusable, and the Guard stays focused on orchestration.

### 6. Shield as central entry point

**Improvement.** Type-safe singleton that holds all configuration, boot logic, and token-prefix methods. Validates at construction time (catches misconfiguration early), easier to mock in tests.
Replaces scattered `config()` calls and Sanctum's static properties.

### 7. TokenType enum (Bearer vs Remember)

**Improvement.** Adds a semantic dimension Sanctum lacks. Type-specific generation logic and centralized `generate()` method are clean.
Enables different token behaviors per type.

### 8. Null token for session-authenticated users

**Improvement.** Session-authenticated users get `$user->token = null`, bearer-authenticated users get the actual token model instance.
This provides a unified `$user->token` interface without a marker class — `null` vs token instance is the discriminator.
Simpler than Sanctum's `TransientToken` wrapper and avoids an extra class.

### 9. ULIDs over auto-incrementing IDs

**Improvement.** Non-sequential, no information leakage about total count or creation order.
Good default for security-sensitive entities like tokens.

### 10. Login event dispatching

**Lateral improvement.** laravel-auth dispatches Laravel's standard `Login` event for both session and bearer auth,
integrating tightly with the auth event system. Sanctum only fires `TokenAuthenticated`.
Whether this is better depends on whether listeners expect `Login` for API tokens.

### 11. Naming

**Minor improvement.** `StatefulFrontend` vs `EnsureFrontendRequestsAreStateful`, `DynamicGuard` vs `Guard`. Shorter, equally descriptive.

### 12. Hash-based token lookup (no pipe-delimited format)

**Clear improvement.** ULIDs make the `id|token` pipe-delimited format unnecessary.
Sanctum's format was a workaround for auto-incrementing integer PKs where a full-table scan would be expensive.
With ULIDs, a hash-based lookup with a DB index on the token column is the correct approach,
no information leakage from the ID prefix, simpler token format, and equivalent performance.

### 13. Proactive expired token cleanup on auth

**Clear improvement.** Deleting expired tokens during auth attempts complements the MassPrunable scheduled pruning.
Expired tokens are by definition invalid -- deleting them immediately reduces table size and speeds up future lookups.
The `pruneDays` config handles bulk cleanup of tokens that never get hit again.

### 14. Container-bound config (no static state)

**Clear improvement.** `Shield::configure()` accepts the `Application` instance and binds `Shield` as a singleton directly into the container.
No static mutable state, no `$pendingConfig` bridging — standard Laravel DI from the start.

### 15. Configurable middleware stack

**Clear improvement.** `Shield::$middlewares` accepts overrides for `encrypt_cookies`, `validate_csrf_token`, and `authenticate_session`.
Set a key to `null` to remove it. Sanctum's fork hardcodes the middleware list with no override mechanism.

### 16. Extension callbacks via Shield closures

**Clear improvement.** Three extension points matching Sanctum's capabilities, implemented as typed closures on the `Shield` singleton:

- `$extractToken` — custom token extraction (e.g., from query param, custom header). Non-nullable with a default that returns `bearerToken()`. Override to fully own extraction — compose the fallback yourself if needed.
- `$validateToken` — custom token validation (e.g., IP allowlisting). Non-nullable with a default that returns `true` (no-op). Receives the token model and request, runs after standard checks but before `$validateUser`.
- `ActingAsToken` trait — testing convenience that creates a real token and sets the `Authorization` header.

Cleaner than Sanctum's static method approach: closures are type-safe, non-nullable with sensible defaults, and scoped to the config instance.

---

## Regressions in laravel-auth

### 1. HasMany instead of MorphMany (polymorphic)

Sanctum's polymorphic `tokenable` relationship lets any model type (User, Admin, Device) have tokens with one table.
laravel-auth's `BelongsTo`/`HasMany` ties tokens to a single user model.

**Real-world use cases where polymorphic tokens matter:**

- **Multi-guard / multi-model auth** — applications with separate `User`, `Admin`, or `Customer` models that each need API tokens
- **Machine-to-machine / service tokens** — `Team`, `Organization`, or `Application` models issuing tokens for automated integrations
- **Device tokens** — IoT devices, terminals, or kiosks authenticating independently of a user
- **OAuth client credentials** — a `Client` model holding its own bearer tokens
- **Service accounts / bot accounts** — non-human identities that don't fit the user model

**Why it matters:** Adding a second tokenable type after launch requires either a new token table (duplicating schema and logic) or migrating the existing `user_id` foreign key to polymorphic `tokenable_id`/`tokenable_type` columns — both are breaking changes that touch migrations, relationships, and queries.

**The deliberate trade-off:** A direct foreign key (`user_id`) provides referential integrity enforced at the database level and simpler queries (no `WHERE tokenable_type = ...` filtering). For single-user-model applications — which are the majority — this is a genuine simplification, not a regression. The cost only surfaces when a second tokenable model is introduced.

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
| No MorphMany                    | Significant regression |

**Bottom line:** The architectural foundation of laravel-auth is genuinely stronger.
DI over statics, contracts over concrete models, action classes, ULIDs, debounced writes, auto-hashing, built-in pruning.
These are real improvements. No critical regressions remain.
The only remaining regression is polymorphic tokens.
