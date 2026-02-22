# 2. Contract + Trait Pattern

**Status:** Accepted

## Context

Sanctum provides a concrete `PersonalAccessToken` model. Consumers who need to customize it must call `Sanctum::usePersonalAccessTokenModel()` to register a replacement, and extend or reimplement the model. This creates a rigid coupling to a specific class.

## Decision

The package defines contracts (interfaces) and traits for both the token model and the user model:

**Token model:**
- `Contracts\IsAuthToken` — interface with `findByToken()`, `owner()`, `setPlain()`, `touchLastUsedAt()`
- `Concerns\IsAuthToken` — trait providing the full implementation

**User model:**
- `Contracts\HasTokens` — interface with `token()`, `tokens()`, extends `OwnsTokens`
- `Concerns\HasTokens` — trait providing `HasMany`/`HasOne` relationships and `createToken()`

The consumer creates their own model, implements the contract, and uses the trait.

## Rationale

- **Ownership** — The consuming application owns its models completely. No extending a package class, no escape hatch method to swap models.
- **Composability** — Traits compose with other concerns naturally. A model can use `IsAuthToken` alongside `HasUlids`, `HasFactory`, and application-specific traits.
- **Type safety** — Shield validates that `tokenModel` implements `IsAuthToken` and `userModel` implements `OwnsTokens` at construction time, providing clear error messages.
- **Flexibility** — Override any trait method directly in your model class. No need to extend and call `parent::`.

## Alternatives Considered

- **Concrete model with `useModel()` escape hatch** (Sanctum's approach) — Works but creates coupling. Extensions require inheriting from the package model or reimplementing everything.
- **Abstract base class** — Better than concrete but forces single inheritance, limiting composition with other base classes.
- **Trait only (no contract)** — Loses the ability to type-hint against an interface, making Shield's model validation less reliable.
