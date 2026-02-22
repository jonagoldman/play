# 3. Dynamic Guard

**Status:** Accepted

## Context

Applications with both a SPA frontend and a public API need to authenticate requests from two sources: session cookies (SPA) and bearer tokens (API clients). Sanctum solves this with a guard that checks the request origin against stateful domains to decide which path to take. This requires knowing upfront whether a request is "stateful" or not.

## Decision

`DynamicGuard` is registered as the `dynamic` driver and used via `auth:dynamic` middleware. It follows a simple priority:

1. Try each guard in `Shield::$guards` (default: `['session']`). If any returns a user, set `$user->token = null` and return.
2. If no session user, extract the bearer token via `Shield::$extractToken` and run `AuthenticateToken`.
3. Dispatch Laravel's `Login` event for both paths.

A single middleware handles both SPA and API authentication.

## Rationale

- **Simplicity** — One guard, one middleware. No need for separate `auth:web` and `auth:api` middleware groups or conditional guard selection.
- **Session-first** — Session auth is cheaper (no DB lookup per request for the token) and is the expected path for SPA users. Bearer is the fallback for API clients.
- **Clean discrimination** — `$user->token === null` for session auth, `$user->token` is the token model for bearer auth. Downstream code can branch on this without extra abstractions.
- **Event consistency** — Both paths dispatch the standard `Login` event, integrating with Laravel's auth event system (listeners, logging, etc.).

## Alternatives Considered

- **Separate guards per auth method** — Requires configuring multiple middleware and knowing which guard to use per route. More complex for routes that need to accept both.
- **Request-origin-first approach** — Check `Referer`/`Origin` headers to decide the path upfront. Fragile (headers can be absent), and conflates transport concern with auth concern. `StatefulFrontend` middleware handles the session pipeline separately.
- **Single guard with abilities/scopes** — Adds complexity for access control that most applications don't need at the guard level.
