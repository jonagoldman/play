# 5. ULIDs Over Auto-Increment

**Status:** Accepted

## Context

Auto-incrementing integer primary keys leak information: the total number of records, creation order, and creation rate. For security-sensitive entities like authentication tokens, this is undesirable. Additionally, auto-incrementing IDs complicate distributed systems and create the need for workarounds like pipe-delimited token formats (see [ADR 006](006-hash-lookup.md)).

## Decision

All token models use ULID primary keys via Laravel's `HasUlids` trait. The migration uses `$table->ulid('id')->primary()`.

## Rationale

- **No information leakage** — ULIDs don't reveal total token count or creation order to anyone who observes an ID.
- **Sortable** — Unlike UUIDs, ULIDs are lexicographically sortable by creation time, preserving efficient B-tree index behavior.
- **Eliminates pipe-delimited format** — With ULIDs, there's no need for Sanctum's `id|token` format to avoid full-table scans. A unique index on the `token` column with hash-based lookup is sufficient (see [ADR 006](006-hash-lookup.md)).
- **Consistent with application convention** — Most modern Laravel applications use ULIDs or UUIDs for primary keys.

## Alternatives Considered

- **Auto-incrementing integers** — Simple and familiar but leak information and necessitate workarounds for token lookup performance.
- **UUIDs (v4)** — Non-sequential, good for privacy, but random ordering causes index fragmentation. ULIDs avoid this with time-prefix ordering.
- **UUIDs (v7)** — Time-ordered like ULIDs but less compact in string form (36 chars with hyphens vs 26 chars for ULIDs).
