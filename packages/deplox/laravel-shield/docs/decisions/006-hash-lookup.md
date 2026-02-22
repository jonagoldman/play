# 6. Hash-Based Lookup

**Status:** Accepted

## Context

Sanctum uses a pipe-delimited token format (`id|random`) where the `id` prefix enables a fast primary key lookup before comparing the hashed token. This was necessary because Sanctum uses auto-incrementing integer PKs, and a `WHERE token = hash(secret)` query on a large table without the ID prefix would require a full table scan without a proper index.

## Decision

Tokens are looked up via `WHERE token = SHA256(secret)` with a unique index on the `token` column:

```php
public static function findByToken(string $token): ?static
{
    $random = app(Shield::class)->extractRandom($token);

    if ($random === null) {
        return null;
    }

    return static::query()->where('token', hash('sha256', $random))->first();
}
```

No ID prefix or pipe-delimited format.

## Rationale

- **ULIDs eliminate the need** — With ULID primary keys (see [ADR 005](005-ulids-over-autoincrement.md)), there's no information-leaking integer ID that could be used as a lookup prefix. The ID is not embedded in the token.
- **Unique index performance** — A unique index on `token` makes `WHERE token = hash(secret)` an O(1) B-tree lookup, equivalent in performance to a primary key lookup.
- **Simpler token format** — No pipe delimiter to parse, no ID to extract. The token is just `{prefix}{random}{checksum}` (or just `{random}` without a prefix).
- **No ID leakage** — The token never contains the database ID, so observing a token reveals nothing about the record.

## Alternatives Considered

- **Pipe-delimited format** (`id|random`, Sanctum's approach) — Necessary for auto-incrementing IDs but redundant with ULIDs. Adds parsing complexity and leaks the database ID in the token.
- **Separate lookup column** (e.g., a short unique identifier) — Adds schema complexity without meaningful benefit over a direct hash index.
