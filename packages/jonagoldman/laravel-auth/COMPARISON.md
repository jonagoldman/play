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

### 1. IsAuthToken trait vs concrete PersonalAccessToken
**Clear improvement.** Sanctum provides a concrete model and needs `Sanctum::usePersonalAccessTokenModel()` as an escape hatch.
laravel-auth lets the consuming app define its own model and mix in behavior -- more composable, no workaround needed.

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

### 6. AuthConfig DTO with constructor injection
**Improvement.** Type-safe, validates at construction time (catches misconfiguration early), easier to mock in tests.
Replaces scattered `config()` calls and Sanctum's static properties.

### 7. TokenType enum (Bearer vs Remember)
**Improvement.** Adds a semantic dimension Sanctum lacks. Type-specific generation logic and centralized `generate()` method are clean.
Enables different token behaviors per type.

### 8. TransientToken marker class
**Improvement.** Provides a unified `$user->token` interface for both session and bearer auth.
Interesting that the Sanctum fork _removed_ the original TransientToken from upstream, while laravel-auth independently re-created the concept.
Sanctum TransientToken was used differently (for `currentAccessToken()`), but the underlying idea (marking session-authenticated users) is the same.

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

---

## Regressions in laravel-auth

### 1. No extension callbacks
Sanctum provides three extension points:
- `getAccessTokenFromRequestUsing()` -- custom token extraction (e.g., from query param or custom header)
- `authenticateAccessTokensUsing()` -- custom validation (e.g., IP allowlisting)
- `actingAs()` -- testing convenience

All three are absent. The first two matter for real-world customization. `actingAs()` is a significant testing convenience.

### 2. HasMany instead of MorphMany (polymorphic)
Sanctum's polymorphic `tokenable` relationship lets any model type (User, Admin, Device) have tokens with one table.
laravel-auth's `BelongsTo`/`HasMany` ties tokens to a single user model. A second tokenable model would require a second table or refactor.

### 3. No CSRF cookie endpoint
Sanctum's `/sanctum/csrf-cookie` route initializes the CSRF cookie for SPA auth flows.
Without it, first-party SPAs must manually set this up.

### 4. No publishable config or migration
Minor. The static `configure()` + `$pendingConfig` pattern is effectively global mutable state.
A Laravel config file allows `env()` for deployment flexibility and is the conventional approach.

### 6. Hardcoded middleware stack
Minor. Sanctum allows swapping `encrypt_cookies`, `validate_csrf_token`, and `authenticate_session` middleware via config.
laravel-auth hardcodes them.

---

## Summary

| Item                              | Category                |
|-----------------------------------|-------------------------|
| IsAuthToken trait                 | Clear improvement       |
| Auto-hash mutator                 | Clear improvement       |
| Debounced writes                  | Clear improvement       |
| MassPrunable                      | Clear improvement       |
| Action class                      | Clear improvement       |
| AuthConfig DTO                    | Clear improvement       |
| TokenType enum                    | Clear improvement       |
| TransientToken                    | Clear improvement       |
| ULIDs                             | Clear improvement       |
| Hash-based lookup                 | Clear improvement       |
| Proactive expired token cleanup   | Clear improvement       |
| Naming                            | Minor improvement       |
| Login event integration           | Minor improvement       |
| No extension callbacks            | Significant regression  |
| No MorphMany                      | Significant regression  |
| No CSRF endpoint                  | Significant regression  |
| No publishable config             | Minor regression        |
| Hardcoded middleware              | Minor regression        |

**Bottom line:** The architectural foundation of laravel-auth is genuinely stronger.
DI over statics, traits over concrete models, action classes, ULIDs, debounced writes, auto-hashing, built-in pruning.
These are real improvements. No critical regressions remain.
The remaining regressions are extension points, polymorphic tokens, and other non-critical features.
