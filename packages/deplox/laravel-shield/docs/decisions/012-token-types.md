# 12. Token Types

**Status:** Accepted

## Context

Authentication systems often need different kinds of tokens — short-lived API tokens and long-lived remember-me tokens have different security characteristics. Sanctum treats all tokens identically with no semantic distinction.

## Decision

A `TokenType` backed enum distinguishes token types:

```php
enum TokenType: string
{
    case Bearer = 'bearer';
    case Remember = 'remember';

    public function generate(): string
    {
        return match ($this) {
            self::Bearer => Str::random(48),
            self::Remember => Str::random(60),
        };
    }
}
```

The type is stored in the `type` column, cast to the enum on the model, and used by `createToken()` to determine the random string length.

## Rationale

- **Semantic clarity** — Code can branch on token type: different expiration defaults, different revocation policies, different UI presentation.
- **Type-specific generation** — Remember tokens are longer (60 chars) than bearer tokens (48 chars), providing more entropy for tokens that may live longer and face more brute-force exposure.
- **Centralized generation** — The `generate()` method on the enum keeps token creation logic co-located with the type definition. No magic numbers scattered across the codebase.
- **Queryable** — `Token::where('type', TokenType::Bearer)` to find all API tokens. Useful for admin dashboards, bulk revocation, or analytics.
- **Extensible** — Adding a new token type (e.g., `Refresh`) requires adding an enum case and its `generate()` branch.

## Alternatives Considered

- **No type distinction** (Sanctum's approach) — All tokens are identical. No way to query by type or apply type-specific logic without a custom column.
- **String constants** — Lose the benefits of enums: no exhaustive matching, no IDE autocompletion, no type safety.
- **Separate models per type** — Overkill. Token types share the same schema and behavior; only the random length and semantic meaning differ.
