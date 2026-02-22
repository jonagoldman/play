# 13. Null Token for Session Auth

**Status:** Accepted

## Context

When both session and bearer authentication are supported via a single guard, downstream code needs a way to determine which method was used. Sanctum creates a `TransientToken` marker class that is set as the current access token for session-authenticated users. This requires a dedicated class with no real behavior.

## Decision

Session-authenticated users have `$user->token = null`. Bearer-authenticated users have `$user->token` set to the actual `IsAuthToken` model instance. The `DynamicGuard` explicitly sets this:

```php
// Session path
$user->setRelation('token', null);

// Bearer path (in AuthenticateToken)
$user->setRelation('token', $accessToken->withoutRelations());
```

## Rationale

- **No extra class** — `null` vs model instance is a natural discriminator. No `TransientToken` class to define, maintain, or document.
- **Simple checks** — `if ($user->token === null)` is clearer than `if ($user->token instanceof TransientToken)`.
- **Unified interface** — Both auth methods populate the same `token` relation. The presence or absence of a value is the discriminator, not the type.
- **Logout compatibility** — The `Logout` action checks `$user->getRelation('token') instanceof Model` to decide whether to delete a token or invalidate a session. Null naturally takes the session path.

## Alternatives Considered

- **`TransientToken` marker class** (Sanctum's approach) — An extra class that exists solely to indicate "not a real token." Adds a class, a type check, and cognitive overhead for no behavioral benefit.
- **Boolean flag** (`$user->isTokenAuthenticated`) — Adds a property that duplicates information already available via the `token` relation. Two sources of truth.
- **Separate user properties** (`$user->accessToken` vs `$user->sessionAuthenticated`) — Splits the interface and requires checking two properties instead of one.
