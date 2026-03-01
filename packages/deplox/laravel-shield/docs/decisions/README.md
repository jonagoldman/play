# Architecture Decision Records

| # | Title | Status | Summary |
|---|-------|--------|---------|
| [001](001-shield-over-config.md) | Shield Over Config | Accepted | Type-safe singleton replaces scattered config and static state |
| [002](002-contract-trait-pattern.md) | Contract + Trait Pattern | Accepted | Contracts and traits instead of concrete models for composability |
| [003](003-dynamic-guard.md) | Dynamic Guard | Accepted | Session-first, bearer-fallback guard behind single middleware |
| [004](004-token-hashing.md) | Token Hashing | Accepted | Attribute mutator auto-hashes on write, preventing plaintext persistence |
| [005](005-ulids-over-autoincrement.md) | ULIDs Over Auto-Increment | Accepted | Non-sequential, non-leaking primary keys for tokens |
| [006](006-hash-lookup.md) | Hash-Based Lookup | Accepted | Direct hash lookup with unique index instead of pipe-delimited format |
| [007](007-token-prefix-checksum.md) | Token Prefix Checksum | Accepted | CRC32B checksum in decorated tokens catches corruption before DB lookup |
| [008](008-polymorphic-tokens.md) | Polymorphic Tokens | Accepted | Alternative traits pattern for opt-in polymorphic token ownership |
| [009](009-owner-over-tokenable.md) | Owner Over Tokenable | Accepted | "owner" morph name instead of "tokenable" for semantic correctness |
| [010](010-extension-callbacks.md) | Extension Callbacks | Accepted | Non-nullable closures with defaults instead of static methods |
| [011](011-debounced-last-used.md) | Debounced Last Used | Accepted | Configurable debounce for last_used_at writes |
| [012](012-token-types.md) | Token Types | Accepted | TokenType enum for semantic token differentiation |
| [013](013-null-token-session.md) | Null Token for Session Auth | Accepted | Null token relation discriminates session vs bearer auth |
| [014](014-configurable-middleware.md) | Configurable Middleware | Accepted | Overridable middleware stack with null-to-remove pattern |
| [015](015-stateful-domains-config.md) | Stateful Domains Config | Accepted | Environment-driven stateful domains via config and env var |
