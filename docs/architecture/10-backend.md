# Backend Doctrine (Laravel) — Domain · Application · Infrastructure

Load `00-core.md` first. This file details the backend rules, transposed from Malta's `core-backend` + domain/application/infra/persistence/outbox knowledge.

---

## A. Domain layer (pure PHP)

### Aggregates (`Domain/Model/`)
- `final` class, **`private` constructor**. Creation only through **intent-revealing static factories** (`Activity::record(...)`, `TrainingBlock::plan(...)`) — not a generic `create()`.
- Protects **its own invariants**. Receives cross-context data **as parameters** — it never fetches anything.
- Holds a private, mutable props state; exposes behavior methods, not setters.
- **Snapshot round-trip:** `toSnapshot(): array` flattens VOs → primitives (for persistence); `static fromSnapshot(array $s): self` rebuilds via VO `from()` factories **without re-validating** (data already valid in DB).
- Emits events inside behavior methods via `$this->recordEvent(new ActivityRecorded(...))`. Exposes `releaseEvents(): array` (returns + clears).
- Aggregate + child entities only live in `Domain/Model/`. Keep files small (~<120 lines) — extract domain services when logic grows.
- Base class `Cadence\Shared\Domain\AggregateRoot` provides `recordEvent()`/`releaseEvents()` and identity/version metadata.

### Value Objects (`Domain/ValueObject/`)
- `final` class, **`private` constructor**, **`readonly` properties** (immutable).
- `static create(...)` = **validates** and throws a typed exception on invalid input. `static from(...)` = **hydration, no validation** (only where rebuilt from trusted storage).
- **Intent-revealing factory names are preferred over generic `create`/`from`** when they read better: `Distance::fromMeters()`, `Elevation::ofMeters()`, `Pace::fromDistanceAndDuration()`. The hydration (no-validation) factory is named **`fromStorage()`** by convention. The rule that matters: exactly one validating path and one hydration path per VO — the verb may fit the concept.
- Expose `value()` / typed getters; implement `equals(self $o): bool`.
- **Identity VOs** (`ActivityId`, `CycleId`) wrap an ordered UUID: `generate(IdGenerator)` + `fromString(string)`.
- No randomness/time inside a VO — inject or pass in.

### Enums
- PHP **backed enums** (`enum SessionType: string`), `SCREAMING_SNAKE_CASE` cases. One enum per file. Check the shared kernel before creating a duplicate.

### Domain events (`Domain/Event/`)
- Extend `Cadence\Shared\Domain\DomainEvent`. `public const NAME = 'activity.recorded';` (dotted, lowercase, **past tense**).
- Payload = **primitives + backed enums + plain arrays only** — never VOs or entities.
- Immutable `readonly` data. One file per event.

### Ports (`Domain/Port/`)
- **PHP `interface`s**, one per file, no bodies, no framework imports.
- Repository port `{Context}Repository`: `save(Aggregate): void`, `ofId(Id, TenantId): ?Aggregate` (nullable find), batch returns keyed arrays.
- Cross-context **provider** ports are **read-only**, named for the *question they answer* (`CurrentPaceZonesProvider`, not `PaceZoneTableProvider`); every method takes the tenant scope.
- Result/among types (`SaveResult`) co-located with the port.

### Exceptions (`Domain/Exception/`)
- One class per file, extends `Cadence\Shared\Domain\DomainException`.
- Carries an **`ErrorCode` enum case** + message. **No HTTP status.** Codes are prefixed by context (`ACTIVITY_NOT_FOUND`).

### Domain services (`Domain/Service/`)
- Pure: no I/O, no framework, all data via parameters. Kinds: computation (e.g. `PaceCalculator`), precondition, policy, validator. Static pure services may be called from the aggregate.

---

## B. Application layer

### Use cases (`Application/UseCase/`) — pure orchestrators, ZERO business logic
One public `execute(Input): Output`. Ordered steps:
1. Validate execution context (tenant/user).
2. Check operational preconditions (via a **guard service**).
3. Fetch data (repositories / providers).
4. Run **domain** logic (call aggregate behavior — the decision lives there, not here).
5. Persist (repository, inside a transaction with its events — see Outbox).
6. Publish events (`EventPublisher::publishAndClear($aggregate)` **after** commit).
7. Dispatch audit (last step, with `$aggregate->toSnapshot()` + `clock->now()`).
8. Return an explicit Output DTO.

Rules:
- `execute()` ≈ 40–60 lines. **No private methods that hold business logic** (tolerated helpers: `validateInput`, `buildLockKey`, `loadExisting`).
- **Business dependency budget ≤ 5** (the infra socle — `Clock`, `EventPublisher`, `AuditDispatcher`, `LoggerInterface` — doesn't count). More = the use case does too much.
- Controllers pass **primitives**; the use case reconstructs VOs.
- Input/Output declared as small `readonly` DTO classes next to the use case.

### Guard services (`Application/Service/`)
- Pattern: **"Guard queries, aggregate enforces."** The guard fetches cross-context state via provider ports and **returns values** (bools/counts/statuses). The aggregate receives them as parameters and throws on invariant violation. The guard's `ensure*` methods may throw only for **operational** preconditions, never for aggregate invariants.
- Defines a shared `ExecutionContext` (tenant + user) consumed by use cases.

### Event handlers (`Application/Handler/`)
- Delegate to a use case (`->execute()`), nothing more. **Idempotent**, **never throw** (catch → log → return early), run as a system user.

---

## C. Infrastructure layer

### Persistence (`Infrastructure/Persistence/Eloquent/`)
- **Eloquent models are infrastructure**, not the domain. They map tables ↔ snapshots. The domain never imports an Eloquent model.
- Repository adapter implements the domain port: `save()` maps `toSnapshot()` → model attributes; reads map model → `Aggregate::fromSnapshot()` (**never** `new`/`::record()` on hydration; version preserved).
- **Every query is tenant-scoped** (`where('tenant_id', $tenant)`). Use `->first()`/`->firstOrFail()` on `id + tenant_id`, never a bare primary-key lookup that ignores tenant.
- **Optimistic locking:** `Model::where('id',$id)->where('version',$v)->update([... ,'version'=>$v+1])`; if affected rows `=== 0` → throw `ConcurrencyException`.
- Wrap failures: `try { } catch (\Throwable $e) { $this->logger->error(...); throw ... }`. Never leak the raw DB error to the caller.
- Enum columns cast via `$casts`; JSON via `array`/`AsArrayObject`; distinguish `null` explicitly.

### Migrations
- `$table->uuid('id')->primary();` `$table->string('x', N)` (explicit length); `$table->foreignUuid(...)`; `$table->softDeletes()` **except** on event/audit tables; index `tenant_id` and every FK.
- Event tables: no `deleted_at`; `$table->unique(['aggregate_id','version']);` `$table->index(['published','occurred_at']);`.
- Prefer `string` columns for enums over DB enums. Migrations reversible.

### HTTP (`Infrastructure/Http/`) — thin controllers
- One controller action per endpoint. It: reads validated input from a **Form Request**, pulls tenant/user from the auth guard, calls **one** use case, returns an **Inertia response** (web) or **API Resource**/`204`.
- **No** business logic, no branching on business type, no direct DB access, no `try/catch` (the global `Handler` maps exceptions).
- Routes are **named** (`route('activities.store')`); use **Ziggy** to expose names to React. No hardcoded URL strings.
- Delete = `->noContent()` (204).

### Contract / DTO layer
- The shared **`ErrorCode` enum** is the FE↔BE source of truth for error handling.
- Validation lives in **Form Requests** (`rules()` with `required`, `uuid`, `integer`, `min`, `max`, `Rule::enum(...)`). Response shape lives in **API Resources** / `spatie/laravel-data` objects with explicitly exposed fields. Never leak an Eloquent model straight to the client.
- Types shared with React are generated from the data objects (`spatie/laravel-typescript-transformer`) — never hand-maintained twice.

### Wiring (`Infrastructure/{Context}ServiceProvider.php`)
- Binds **every port → adapter** (`$this->app->bind(ActivityRepository::class, EloquentActivityRepository::class)`).
- Registers use cases in the container (closure factories); constructs `EventPublisher` per use case where the Malta pattern demands a fresh instance.
- No circular dependencies. The provider is registered in `config/app.php` / `bootstrap/providers.php`.

---

## D. The Outbox pattern (framework-agnostic → Laravel queue)

1. **Atomic write:** in `repository->save()`, persist the aggregate row **and** its released domain events into `outbox_events` **inside the same `DB::transaction()`**, with a **sequential per-aggregate `version`**. Events come from `$aggregate->releaseEvents()`, never hand-built.
2. **Publish after commit:** `EventPublisher::publishAndClear()` runs post-commit, **best-effort, never throws**. A publish failure leaves `published=false`; the aggregate is still safely saved.
3. **`outbox_events` columns:** `id`, `aggregate_id`, `aggregate_type`, `tenant_id`, `event_name`, `payload` (json), `user_id`, `version`, `occurred_at`, `published` (bool), `published_at`. Constraints: `unique(aggregate_id, version)`, `index(published, occurred_at)`, partial-index intent on `published = false`.
4. **Dispatch:** a `PublishOutboxJob` (queued, scheduled catch-up every minute) reads unpublished rows in `aggregate_id, version ASC`, dispatches a **queued listener/job per event**, then marks them published in **one batch update**. Jobs are `ShouldBeUnique` for dedup.
5. **Consumers/handlers idempotent:** check-before-write / upsert / conditional update; skip events with `version < ` already-processed; never throw (failures → retry/`failed_jobs`, safe replay).
6. **Read models / projections:** a projector updates a denormalized table via **idempotent upsert** (e.g. `personal_records`, weekly-load rollups). Read-model tables carry no `version`/`deleted_at`; include a `searchable_text` where list-search is needed.

---

## E. Backend definition of done

- [ ] Domain pure (arch test green: `Domain/` imports no `Illuminate`, no Eloquent).
- [ ] Use case has zero business logic, ≤5 business deps, audits every mutation.
- [ ] Every query tenant-scoped; optimistic locking on updates.
- [ ] Events written in the same transaction; publish is best-effort.
- [ ] Typed exceptions with `ErrorCode`; no HTTP status in domain.
- [ ] Larastan level 8 (strict) green; Pest + arch tests green.
