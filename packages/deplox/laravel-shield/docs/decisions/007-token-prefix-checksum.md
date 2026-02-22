# 7. Token Prefix Checksum

**Status:** Accepted

## Context

GitHub and other platforms use prefixed tokens (e.g., `ghp_`, `gho_`) to enable automated secret scanning. A prefix alone doesn't validate that the token is complete and uncorrupted — truncated tokens would still pass the prefix check and hit the database for a failed lookup.

## Decision

Decorated tokens follow the format `{prefix}{random}{crc32b(random)}`:

```php
public function decorateToken(string $random): string
{
    if ($this->prefix === '') {
        return $random;
    }

    return $this->prefix.$random.hash('crc32b', $random);
}
```

The `extractRandom()` method strips the prefix, validates the CRC32B checksum (always 8 hex characters), and returns the random part. Returns `null` if the prefix is missing or the checksum doesn't match.

When no prefix is configured, the token is the raw random string with no decoration.

## Rationale

- **Secret scanning** — The prefix enables tools like GitHub secret scanning to identify leaked tokens in repositories.
- **Corruption detection** — CRC32B catches truncated or corrupted tokens before they reach the database. A copy-paste error that drops trailing characters will fail the checksum, avoiding a wasted database lookup.
- **Cheap** — CRC32B is fast and produces a fixed 8-character hex string. Negligible overhead compared to the SHA256 hash and database query.
- **Opt-in** — No prefix means no decoration. The raw random string is used directly. Existing applications can adopt prefixes incrementally.

## Alternatives Considered

- **Prefix only (no checksum)** — Simpler but doesn't catch truncation. Truncated tokens would still pass the prefix check and hit the database.
- **SHA256 checksum** — Overkill for integrity checking. CRC32B is sufficient for detecting accidental corruption (not adversarial tampering — the full SHA256 hash in the database handles that).
- **HMAC signature** — Adds security but requires a secret key, adds complexity, and is unnecessary when the database already stores a full SHA256 hash of the random part.
