# Core Architecture Doctrine — Cadence

> Single source of truth for **every** agent and every contributor. Ported from the Malta `warehouse-orch` doctrine (TypeScript/NestJS/Prisma) and **faithfully transposed to Laravel 13 + Eloquent + Inertia/React**. All code and identifiers are in **English**.

Cadence is a **modular monolith** built with **Hexagonal Architecture + DDD + Clean Architecture**, developed **test-first (TDD)**.

## 1. The Golden Rules

1. **Dependencies point inward only: `Infrastructure → Application → Domain`. Never the reverse.**
2. **The Domain layer is pure PHP.** Zero framework imports — no `Illuminate\*`, no Eloquent, no facades, no `Carbon` calls to `now()`, no container. A domain file compiles and runs with nothing but PHP + the shared kernel.
3. **Depend on abstractions, inject them.** No static calls to non-deterministic sources in business code: never `now()`, `Carbon::now()`, `Str::uuid()`, `random_int()`, `Str::random()` inline — always go through an injected port (`Clock`, `IdGenerator`, `RandomGenerator`).
4. **Single source of truth.** One concept, one place. Duplication is drift waiting to happen.
5. **Ubiquitous language everywhere.** Names come from the training domain (`Activity`, `PaceZone`, `TrainingBlock`, `recordActivity`), never technical mush (`Data`, `Info`, `Manager`, `processInput`, `handle`).
6. **When in doubt, ASK.** Never guess a business rule or invent a placeholder.

## 2. Layers & dependency direction

```
┌─────────────────────────────────────────────────────────────┐
│ Infrastructure  (Eloquent, HTTP controllers, queue, adapters)│  ← knows Application + Domain
│   ┌─────────────────────────────────────────────────────┐   │
│   │ Application  (use cases, guard services, handlers)    │   │  ← knows Domain only
│   │   ┌─────────────────────────────────────────────┐   │   │
│   │   │ Domain  (aggregates, VOs, events, ports)      │   │   │  ← knows nothing outside itself
│   │   └─────────────────────────────────────────────┘   │   │
│   └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

- **Domain** — business truth. Aggregates, entities, value objects, domain events, **ports** (PHP `interface`s), domain services, typed exceptions. No I/O, no framework.
- **Application** — orchestration. Use cases are *pure orchestrators* (no business logic), guard services (query cross-context state), event handlers. Speaks to the domain and to ports.
- **Infrastructure** — the outside world. Eloquent repositories implementing domain ports, HTTP/Inertia controllers, Form Requests, queued jobs (outbox/consumers), service-provider wiring, the contract/DTO layer.

> **PHP note (vs the TS original):** Malta uses *abstract classes* as ports because TS interfaces are erased at runtime and can't be DI tokens. **PHP interfaces persist at runtime**, so in Cadence **ports are `interface`s**, bound to concrete adapters in a **bounded-context Service Provider** (`$this->app->bind(Port::class, Adapter::class)`).

## 3. Bounded contexts

The domain is split into **bounded contexts** (BCs). Each is self-contained and owns its own layers. A BC never reaches into another BC's internals — cross-context reads go through **read-only provider ports**; cross-context writes happen through **domain events + handlers** (never a direct write).

Initial contexts (see `docs/architecture/50-domain-map.md`):
`Training` (blocks, cycles, planned sessions) · `Activity` (runs done, splits, best efforts, PRs) · `Metrics` (weight, sleep, HR, load, injuries) · `Goals` (target races, records) · `Journal` (blog).

### Folder layout per bounded context

```
src/{Context}/
  Domain/
    Model/            # Aggregate roots + child entities only
    ValueObject/      # Identity VOs + domain VOs
    Event/            # Domain events
    Port/             # Interfaces: repositories, cross-context providers
    Service/          # Pure domain services
    Exception/        # One class per file
  Application/
    UseCase/          # One public execute() each
    Service/          # Guard services + application services
    Handler/          # Domain-event handlers
  Infrastructure/
    Persistence/
      Eloquent/       # Eloquent models (framework-facing) + repositories
    Http/
      Controller/     # Thin controllers
      Request/        # Form Requests
      Resource/       # Response DTOs / Inertia props
    Provider/         # Cross-context provider adapters
    Job/              # Outbox publisher + consumers
    {Context}ServiceProvider.php   # Wiring: bind ports → adapters, register use cases
```

`app/` keeps only Laravel's framework glue (kernel, global middleware, base provider). Business code lives under `src/` (PSR-4 `Cadence\\` → `src/`).

## 4. Naming conventions

| Element | Convention | Example |
|---|---|---|
| Classes / types | `PascalCase` | `Activity`, `PaceZone`, `RecordActivityUseCase` |
| Methods / vars | `camelCase` | `recordActivity()`, `$movingTime` |
| Constants / enum cases | `SCREAMING_SNAKE_CASE` | `EASY_RUN`, `THRESHOLD` |
| Files | one class per file, `PascalCase.php` | `RecordActivityUseCase.php` |
| Use case | `{Verb}{Noun}UseCase` | `PlanSessionUseCase`, `ListActivitiesUseCase` |
| Value object | `{Noun}` in `ValueObject/` | `Pace`, `Distance`, `ActivityId` |
| Domain event | `{concept}.{action}` past tense (string) | `activity.recorded`, `cycle.completed` |
| Repository port | `{Context}Repository` (interface) | `ActivityRepository` |
| Provider port | `{Question}Provider` (interface) | `CurrentPaceZonesProvider` |
| Migrations / tables | snake_case, plural | `activities`, `activity_splits` |
| Commits | Conventional Commits | `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:` |

## 5. Determinism budget (business code)

Anything time-, id-, or randomness-dependent is injected:

- **Clock** — `Cadence\Shared\Clock\Clock` interface; `SystemClock` in prod, `FixedClock` in tests. Business code calls `$this->clock->now()`.
- **IdGenerator** — wraps `Str::orderedUuid()`; `FixedIdGenerator` in tests.
- **RandomGenerator** — wraps `random_int`/`Str::random`; fixed in tests.

If a use-case or domain test can't assert an exact value, a raw static call leaked in. That's a bug.

## 6. Error handling

- Business errors are **typed exceptions** carrying an **enum `ErrorCode`** + message. Never `throw new \Exception('...')` in business code.
- The `ErrorCode` enum is the **contract source of truth**, shared by backend and frontend. Domain exceptions carry the code; **no HTTP status in the domain** — the `code → HTTP status` mapping lives in `app/Exceptions/Handler.php`.
- Never swallow errors silently (no empty `catch`, no masking `?? $default` that hides a failure).
- Each layer translates errors into its own vocabulary: infra failure → domain/application exception → user-facing response. The frontend business layer returns a **Result**, it does not throw.

## 7. Quality gates (the TDD loop)

Every change passes, in order:

1. `composer stan` — **Larastan/PHPStan** (level 8, strict) — the "does it type-check" gate.
2. `composer test:arch` — **Pest architecture tests** — enforce layer boundaries (Domain imports no framework, Infrastructure never imported by Domain, etc.).
3. `composer test` — **Pest** unit (use-case level) + feature/E2E.

A red gate blocks the phase. Then an **adversarial code review** (see `40-review.md`) runs before documentation. See per-domain doctrine in `10-backend.md`, `20-frontend.md`, `30-testing.md`, `40-review.md`.
