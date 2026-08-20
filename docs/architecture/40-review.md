# Review Doctrine — dimensions, severities, verdict

Load `00-core.md` first. Transposed from Malta's `review` agent + `malta-review-*` commands. Reviews are **read-only** — a reviewer produces a report, never edits code.

## Severities
- 🔴 **Critical** — must fix before merge. **Any multi-tenant isolation gap is ALWAYS critical.** A domain reaching an invalid state is critical.
- 🟠 **Important** — should fix; architectural or correctness debt.
- 🟡 **Minor** — style / polish.

## Verdict
- **PASS** requires **0 critical** findings.
- Every finding cites: exact `file:line`, the **rule violated** (link to the doctrine section), the concrete **WHY**, and a concrete fix.
- "A review with zero findings is a failed review" — if you found nothing, you didn't look hard enough. When unsure of severity, escalate.

## Smoke gate (before any review)
`composer stan` must pass and `composer test` must be green. A failing gate = automatic 🔴 and the review is skipped until fixed.

## Review dimensions (run the relevant ones; `full` runs all in parallel)

- **[DOM] Domain purity** — factories intent-revealing; cross-context data passed as params; `toSnapshot()/fromSnapshot()` correct; events carry required primitive payloads; **no framework import in Domain**; VOs immutable (`readonly`, private ctor, `create()`/`from()`); missing-VO detection (primitive obsession); enums backed + one per file; exceptions carry `ErrorCode`, no raw `throw new Exception`.
- **[UC] Use cases** — single `execute()`; ordered steps; **no inline business logic** (no value-deciding if/switch); **no private business methods**; ≤5 business deps; determinism (no `now()`/`uuid()`/`random`); guard-queries/aggregate-enforces; events published post-commit; **every mutation audited**; explicit Output DTO.
- **[PER] Persistence** — snapshot mapping explicit & fully typed (no `mixed`/`array` shrug); hydration via `fromSnapshot()` (never `new`); **optimistic locking** (`version` check → `ConcurrencyException`); **`tenant_id` in EVERY query**; multi-table writes + events in one transaction; catch/log/rethrow with context; migrations: soft deletes, indexes, `unique(aggregate_id,version)`.
- **[CTL] Controllers** — one action = one endpoint; no private methods / branching / business validation / direct DB / try-catch / logging; validation via Form Request; named routes (no hardcoded URLs); correct HTTP status (201 / 204 / 200); authorization via policy/middleware.
- **[WIR] Wiring** — every port bound to an adapter in a context ServiceProvider; use-case factories complete (all ctor deps provided); no circular deps; provider registered; no orphan bindings.
- **[OUT] Outbox** — events saved in same transaction from `releaseEvents()`; sequential `version`; publisher best-effort/no-throw; `outbox_events` columns + `unique(aggregate_id,version)` + index; consumers idempotent, skip stale versions; catch-up job batched & ordered.
- **[TST] Tests** — reads source first; every branch/`throw`/side-effect covered; **fakes only** (no Mockery/spies in unit); determinism (FixedClock/Id); BDD ubiquitous-language names; cross-tenant test present; outbox tests present; flags tests that assert only status without the side effect.
- **[FE] Frontend** — no business logic in components; Container/View split; `Result` everywhere; i18n complete; layer-import rules; no `useEffect` state-sync smell; generated types not hand-duplicated.
- **[SEC] Security / adversarial** — authz on every route; no mass-assignment; no tenant bypass; no secret in code/logs; input validated; no SQL string concatenation; rate-limited auth.

## Recurring-anti-pattern capture
After a review, a reviewer may propose **0–2** genuinely recurring anti-patterns, written (only with user approval) to `docs/architecture/anti-patterns/{name}.md` (Symptom ❌ / Fix ✅ / The Rule, ≤80 lines). Never auto-create; never duplicate an existing one.
