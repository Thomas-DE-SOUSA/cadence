# Testing Doctrine (Pest) — TDD, fakes-only, arch tests

Load `00-core.md` first. Transposed from Malta's test-engineer + use-case-tests + e2e knowledge. Runner: **Pest** (PHPUnit under the hood).

## 1. The TDD loop

Red → Green → Refactor, at the **use-case level**:
1. Write a failing use-case test that states a business behavior.
2. Write the minimum domain + application code to pass.
3. Refactor under green. Re-run `composer stan && composer test:arch && composer test`.

Never write production code without a failing test first (exception: trivial wiring covered by a feature test).

## 2. The test pyramid

| Level | Tool | Scope | Doubles |
|---|---|---|---|
| **Use-case (unit)** | Pest `tests/Unit` | One use case, full domain exercised through it | **Fakes only** |
| **Architecture** | Pest arch plugin | Layer boundaries, naming, purity | — |
| **Feature / E2E** | Pest `tests/Feature` | HTTP route → DB, real container | Real, spies allowed for infra failures |

### Rule: test at the use-case level, not in isolation
- **No isolated VO/entity/domain-service tests.** They are exercised through the use case that uses them. (A pure calculator with heavy branching may get its own test — judgment.)
- This keeps tests behavioral and refactor-proof.

## 3. Fakes only (hard rule for unit tests)

- **Forbidden in use-case tests:** Mockery, `->shouldReceive()`, spies, partial mocks, `mock()`. If you're asserting "method X was called", you're testing implementation, not behavior.
- **Use in-memory fakes** implementing the domain ports: `InMemoryActivityRepository implements ActivityRepository`. They store in an array and are **real** implementations you can query for state.
- A shared `TestContext` builds the fakes + the use case under test. `beforeEach` calls `$ctx->reset()` which **clears** repositories (not re-instantiates).
- Spies/mocks **are** allowed in Feature/E2E tests to simulate infra failures (queue down, DB error).

## 4. Determinism

- Time: inject `FixedClock` (`->setNow('2026-08-19T18:00:00Z')`). Never let code call `now()`.
- Ids: `FixedIdGenerator` returning known UUIDs. Assert exact ids; use `expect(...)->toBeUuid()` only for genuinely generated-then-unknown values.
- Randomness: `FixedRandomGenerator`.
- Clone inputs in fluent builders to avoid shared-state bleed.

## 5. Naming (BDD, ubiquitous language)

```php
describe('Feature: Recording an activity', function () {
    it('records a run with its kilometre splits and derived average pace', ...);
    it('rejects an activity whose distance is zero', ...);
    it('is isolated per tenant: another tenant cannot read the activity', ...);
});
```
No `should`, no HTTP codes, no "calls repository". Describe **business behavior**.

## 6. Mandatory coverage per use case

- Every branch / every `throw` (with its `ErrorCode`) has a test (mutation-testing mindset: change one line → a test breaks).
- Every side effect asserted: aggregate saved, **events written**, audit dispatched.
- **≥1 cross-tenant isolation test** (other tenant → null / 404).
- **Outbox tests for every mutation** (adapted set of 5):
  1. the domain event is written to `outbox_events` in the same transaction;
  2. the aggregate is still saved when the publisher throws;
  3. the publisher failure does not bubble out of the use case;
  4. events carry sequential `version`s;
  5. re-running the handler is idempotent (no duplicate effect).
- Event handler specs: system user, idempotency, early-return on already-processed.

## 7. Feature / E2E

- Real HTTP through named routes; `RefreshDatabase`.
- Happy path + critical edges (not exhaustive — business logic is covered by unit tests).
- **Cross-tenant access returns 404, not 403.**
- Exact status codes (`->assertStatus(201)`, `->assertNoContent()`), never `>= 400`.
- Assert the **side effect**, not just the response (row created, event queued).
- Test outbox + multi-tenancy on every mutation endpoint.

## 8. Architecture tests (`composer test:arch`)

Encode the golden rules as executable Pest arch expectations, e.g.:
```php
arch('domain is pure')
    ->expect('Cadence\*\Domain')
    ->not->toUse(['Illuminate', 'Cadence\*\Infrastructure', 'Cadence\*\Application']);

arch('application never touches Eloquent')
    ->expect('Cadence\*\Application')
    ->not->toUse('Illuminate\Database\Eloquent');

arch('use cases end in UseCase and are final')
    ->expect('Cadence\*\Application\UseCase')->toHaveSuffix('UseCase')->toBeFinal();

arch('no debugging leftovers')->expect(['dd','dump','ray','var_dump'])->not->toBeUsed();
```
These are non-negotiable gates — they make the hexagon self-enforcing.
