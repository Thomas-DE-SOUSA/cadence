# Cadence

Personal endurance-training log & planner. From a 10 km road block to a 1-year trail objective. Self-hosted (Docker on a Raspberry Pi alongside Home Assistant), private access via Tailscale, installable as a **PWA** on Android.

**All code, identifiers, comments and docs are in English.**

## Stack

- **Backend:** Laravel 13 (PHP 8.3+), Eloquent, SQLite.
- **Frontend:** Inertia + React + TypeScript + Vite + Tailwind (PWA).
- **Admin:** Filament (`/admin`) for fast data management.
- **Tests:** Pest (unit + feature + architecture), Larastan/PHPStan.
- **Dev:** Laravel Sail via OrbStack. **Prod:** single Docker image (FrankenPHP) on the Pi.

## Architecture — read this first

Cadence is a **modular monolith** with **Hexagonal Architecture + DDD + Clean Architecture**, built **test-first (TDD)**. The doctrine is the source of truth — read before writing code:

- `docs/architecture/00-core.md` — golden rules, layers, bounded contexts, naming, determinism.
- `docs/architecture/10-backend.md` — domain / application / infrastructure / persistence / outbox.
- `docs/architecture/20-frontend.md` — Inertia/React clean architecture.
- `docs/architecture/30-testing.md` — Pest TDD, fakes-only, arch tests.
- `docs/architecture/40-review.md` — review dimensions & severities.
- `docs/architecture/50-domain-map.md` — bounded contexts, aggregates, invariants, events.

Business code lives under `src/` (PSR-4 `Cadence\` → `src/`), split by bounded context, each with `Domain / Application / Infrastructure`. `app/` holds only Laravel glue.

## The agent fleet (`.claude/agents/`)

A pipeline ported from the Malta `warehouse-orch`, transposed to Laravel. Each agent loads the doctrine, owns one layer, and hands off to the next:

| Agent | Persona | Owns |
|---|---|---|
| `domain-architect` | Atlas 🏛️ | Pure domain: aggregates, VOs, events, ports, invariants |
| `application-engineer` | Nova ⚙️ | Use cases (pure orchestrators), guards, handlers |
| `infrastructure-engineer` | Forge 🔧 | Eloquent, migrations, controllers, jobs, wiring |
| `test-engineer` | Sentinel 🛡️ | Pest tests (TDD, fakes-only, outbox, multi-tenant) |
| `frontend-architect` | Prism 🌐 | Inertia/React, Container/View, Result pattern |
| `database-reviewer` | Oracle 🗄️ | Schema/query/index review (read-only) |
| `documentation-writer` | Scribe 📜 | Per-context `CLAUDE.md`, docs |
| `code-reviewer` | The Gadfly 🪰 | Adversarial architecture + security review (read-only) |

### Standard pipeline for a feature/bounded context
1. **Sentinel** writes a failing use-case test (red).
2. **Atlas** builds the pure domain (aggregate/VOs/events/ports).
3. **Nova** writes the use case (pure orchestration) → green.
4. **Forge** adds Eloquent repo + migration + controller + wiring + outbox.
5. **Oracle** reviews the schema/queries.
6. **The Gadfly** runs an adversarial review (smoke gate → dimensions → verdict).
7. **Scribe** documents the context.

Gate every step: `composer stan && composer test:arch && composer test`.

## Commands

```bash
composer stan        # Larastan/PHPStan (level 8, strict)
composer test        # Pest unit + feature
composer test:arch   # Pest architecture tests (layer boundaries)
./vendor/bin/sail up # local dev via OrbStack
```

## Conventions

- **TDD** — failing test first. **English** everywhere. **Conventional Commits** (`feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`).
- Tenant-scoped from day one. Typed exceptions with `ErrorCode`. No static `now()`/`uuid()`/`random` in business code — inject `Clock`/`IdGenerator`/`RandomGenerator`.
- When a business rule is unclear, **ask** — never guess.
