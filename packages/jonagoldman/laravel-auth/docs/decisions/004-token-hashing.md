# 4. Token Hashing

**Status:** Accepted

## Context

Tokens are sensitive secrets that must never be stored in plaintext. Sanctum hashes tokens in the `createToken()` method, meaning the caller is responsible for ensuring hashing happens. If a token is created through any other code path (e.g., direct factory creation, seeder, manual insert), it could be stored in plaintext.

## Decision

The `IsAuthToken` trait defines a `token` Attribute mutator that hashes the value with SHA256 on every write:

```php
protected function token(): Attribute
{
    return Attribute::make(set: fn ($value) => hash('sha256', $this->plain = $value));
}
```

Any assignment to `$token->token` automatically hashes the value. The raw value is simultaneously captured in `$this->plain` for the response.

## Rationale

- **Impossible to bypass** — Regardless of how or where a token value is set (create, update, factory, seeder, tinker), the mutator fires and the stored value is always a hash.
- **Single responsibility** — Hashing is the model's concern, not the caller's. `createToken()` just sets the raw random string; the model handles the rest.
- **Side-effect capture** — The mutator simultaneously stores the raw value in `$this->plain`, eliminating the need for a separate step to capture the plaintext for the response.

## Alternatives Considered

- **Hash in `createToken()` only** (Sanctum's approach) — Works for the happy path but leaves other code paths (factories, direct inserts) vulnerable to storing plaintext.
- **Hash in a model event** (`creating`/`saving`) — Fires too late in the lifecycle. You'd need the raw value before the event fires to return it to the user, requiring a separate mechanism.
- **Bcrypt/Argon2 instead of SHA256** — Unnecessary for tokens. Tokens are high-entropy random strings (48-60 chars), not user-chosen passwords. SHA256 is computationally cheaper and sufficient for this use case.
