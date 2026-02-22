# 9. Owner Over Tokenable

**Status:** Accepted

## Context

Sanctum names its polymorphic relationship `tokenable` — the model that is "token-able" (can have tokens). This follows Laravel's convention of using the `-able` suffix for morph relationships (e.g., `commentable`, `taggable`). However, the `-able` suffix means "thing that can be [verbed]," and in this case, the semantics are backwards: the user is not "tokenable" (able to be tokened), the user *owns* tokens.

## Decision

The relationship is named `owner` — `$token->owner` returns the user (or other authenticatable model) that owns the token.

The morph column names follow: `owner_id` and `owner_type` in the polymorphic migration.

## Rationale

- **Semantic correctness** — `owner` describes the relationship from the token's perspective: "who owns this token?" The `-able` suffix would require `ownable` to be correct, which means "capable of being owned" — the opposite of the intended meaning.
- **Clarity** — `$token->owner` reads naturally. `$token->tokenable` is self-referential and confusing: a token's tokenable is... the thing that has tokens?
- **Consistency** — The inverse relationships are `$user->tokens()` and `$user->token()`. The singular `owner` pairs naturally with the plural `tokens`.

## Alternatives Considered

- **`tokenable`** (Sanctum's approach) — Established convention but semantically incorrect. Confusing for new developers reading the codebase.
- **`user`** — Too specific. With polymorphic support, the owner might be a `Team`, `Admin`, or other model. `owner` is generic enough for all cases.
- **`authenticatable`** — Accurate but verbose. `$token->authenticatable` is cumbersome.
