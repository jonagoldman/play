# 11. Debounced Last Used

**Status:** Accepted

## Context

Tracking when a token was last used is valuable for security auditing and token lifecycle management. Writing `last_used_at` on every authenticated request creates unnecessary database write pressure, especially for high-traffic APIs where a single token might authenticate dozens of requests per minute.

## Decision

`touchLastUsedAt()` checks the elapsed time since the last update and skips the write if it's within the debounce window:

```php
public function touchLastUsedAt(): void
{
    $debounce = app(Shield::class)->lastUsedAtDebounce;

    if ($this->last_used_at && $this->last_used_at->diffInSeconds(now()) < $debounce) {
        return;
    }

    // ...update last_used_at...
}
```

The debounce period is configurable via `Shield::$lastUsedAtDebounce` (default: 300 seconds / 5 minutes).

The method also preserves the connection's record modification state to avoid interfering with Laravel's `hasModifiedRecords` tracking.

## Rationale

- **Write reduction** — A token used 100 times per minute generates 1 write per 5 minutes instead of 100. Dramatically reduces database write volume.
- **Negligible accuracy loss** — For security auditing, knowing a token was used "within the last 5 minutes" is sufficient. Exact-second precision on `last_used_at` provides no meaningful security benefit.
- **Configurable** — Applications with different requirements can tune the debounce. Set to `0` for per-request writes, or increase for lower write volume.
- **Non-intrusive** — Uses `saveQuietly()` to avoid dispatching model events and preserves the connection's modification state, so the write doesn't affect application-level dirty tracking.

## Alternatives Considered

- **Per-request writes** (no debounce) — Simple but wasteful. Creates one write per authenticated request regardless of frequency.
- **Boolean toggle** (Sanctum fork's approach) — Either writes every time or never. No middle ground. Most applications want some tracking without the full write cost.
- **Queue-based updates** — Adds infrastructure dependency and complexity. The debounce achieves the same write reduction with zero infrastructure changes.
- **Cache-based debounce** — Would avoid the database read to check the last timestamp, but adds a cache dependency and introduces consistency issues if the cache is cleared.
