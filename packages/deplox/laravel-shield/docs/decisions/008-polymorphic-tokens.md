# 8. Polymorphic Tokens

**Status:** Accepted

## Context

Some applications need multiple model types (e.g., `User`, `Admin`, `Team`) to own authentication tokens. The default `HasTokens` trait uses a direct foreign key (`user_id`), which only supports a single owner model type. Supporting polymorphic ownership requires a different schema and relationship type, but making it the default adds complexity for the common single-model case.

## Decision

Two modes are provided via alternative traits, following Laravel's `HasUuids` vs `HasUlids` pattern:

**Default (direct FK):**
- User model: `HasTokens` contract + trait (`HasMany`/`HasOne`)
- Token model: `IsAuthToken` trait with `owner()` returning `BelongsTo`
- Migration: `foreignUlid('user_id')` with constraint
- No schema changes needed

**Opt-in polymorphic:**
- User model: `HasMorphTokens` contract + trait (`MorphMany`/`MorphOne`)
- Token model: Override `owner()` to return `MorphTo` (covariant, since `MorphTo extends BelongsTo`)
- Migration: `ulidMorphs('owner')` — publish via `laravel-shield-morph-migrations` tag

Both traits share `CreatesTokens` for the `createToken()` implementation. Both satisfy the `OwnsTokens` marker contract that Shield validates against.

## Rationale

- **Simplicity by default** — Direct FK is simpler, has database-level referential integrity via constraints, and better query performance. Most applications only have one authenticatable model.
- **No breaking changes** — Adding polymorphic support didn't change the default behavior. Existing installations continue to work unchanged.
- **Familiar pattern** — Follows Laravel's own convention of offering alternative traits for different ID strategies (`HasUuids` vs `HasUlids`). Developers already understand this pattern.
- **Covariant override** — The token model only needs to override `owner()` from `BelongsTo` to `MorphTo`. Since `MorphTo extends BelongsTo`, this is a covariant return type and satisfies the `IsAuthToken` contract without a separate trait on the token model.

## Alternatives Considered

- **Polymorphic by default** — Adds unnecessary complexity (morphable columns, no FK constraints, extra index) for applications with a single authenticatable model.
- **Configuration flag** (`polymorphic: true`) — Runtime branching in the trait based on config. Harder to type-check, messier code paths, doesn't follow Laravel conventions.
- **Separate token model trait for polymorphic** — Unnecessary because `MorphTo extends BelongsTo`, so a simple method override suffices. Would create needless duplication.
