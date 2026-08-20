# Domain Map — Cadence

The ubiquitous language and bounded contexts for a personal endurance-training log. Scope: from a 10 km road block to a 1-year trail objective. Single athlete today, but **tenant-scoped from day one** (`tenant_id` everywhere) so it never has to be retrofitted.

## Ubiquitous language

| Term | Meaning |
|---|---|
| **Activity** | A run that actually happened (manual or imported from Strava). |
| **Split** | One kilometre (or segment) of an Activity, with its pace and elevation delta. |
| **Best effort** | A rolling-window best inside an Activity (e.g. best 5 km) — Strava-style. |
| **Personal record (PR)** | The athlete's current best time for a distance, across all activities. |
| **Planned session** | A prescribed workout on a date (easy, threshold, intervals, race-pace, cross, rest). |
| **Cycle** | A mesocycle (e.g. *C1 Adaptation*) with an objective and an exit criterion. |
| **Training block** | A macrocycle grouping cycles toward one target race. |
| **Target race** | A race the athlete is training for (distance, elevation, date, priority, goal time). |
| **Pace zone** | A training-intensity band (recovery → intervals) with a pace range and RPE. |
| **Metric** | A daily personal datapoint (weight, resting HR, sleep, fatigue, soreness). |
| **Training load** | Session load = duration × RPE (sRPE); rolled up to acute/chronic ratio. |

## Bounded contexts

### 1. `Training` — the plan
- **Aggregates:** `TrainingBlock` (root) → `Cycle` (child) → `PlannedSession` (child). `PaceZoneSet` (root, versioned by effective date).
- **Invariants:**
  - INV-001 A `Cycle`'s date range is within its `TrainingBlock`.
  - INV-002 A `PlannedSession` date is within its `Cycle`.
  - INV-003 Exactly one `PaceZoneSet` is active at any date (no overlap).
  - INV-004 A `Cycle` cannot be marked *completed* while its block is not *active*.
- **Events:** `block.planned`, `cycle.started`, `cycle.completed`, `session.planned`, `pace-zones.recalibrated`.

### 2. `Activity` — what was done
- **Aggregate:** `Activity` (root) → `Split` (child), `BestEffort` (child).
- **Invariants:**
  - INV-010 `distance > 0` and `movingTime > 0`.
  - INV-011 Average pace = movingTime / distance (derived, never stored inconsistently).
  - INV-012 Splits' summed distance ≈ activity distance (tolerance).
  - INV-013 An imported activity is unique per `(tenant, source, externalId)`.
- **Events:** `activity.recorded`, `activity.imported`, `best-effort.set`. A recorded activity may raise `personal-record.beaten` (consumed by `Goals`).
- **Cross-context:** may be linked to a `PlannedSession` (planned vs actual) via a read-only provider — no direct write into `Training`.

### 3. `Metrics` — the body
- **Aggregates:** `DailyMetric` (root, one per date), `Injury` (root).
- **Invariants:**
  - INV-020 One `DailyMetric` per `(tenant, date)`.
  - INV-021 An `Injury` that "modifies stride" flags the day as high-risk.
  - INV-022 Training load for a day = Σ(activity duration × RPE) that day.
- **Events:** `metric.logged`, `injury.opened`, `injury.resolved`.
- **Read model:** `training_load_daily` (acute 7-day vs chronic 28-day ratio) projected from `activity.recorded` + `metric.logged`.

### 4. `Goals` — the ambition
- **Aggregates:** `TargetRace` (root), `PersonalRecord` (root, one per distance).
- **Invariants:**
  - INV-030 At most one **A-priority** target race per overlapping period.
  - INV-031 A `PersonalRecord` only moves when a faster time is proven by an `Activity`.
- **Events:** `race.targeted`, `record.broken`.
- **Consumes:** `personal-record.beaten` from `Activity` → updates `PersonalRecord`.

### 5. `Journal` — the story
- **Aggregate:** `JournalEntry` (root): date, title, body (markdown), tags, optional linked activity.
- **Invariants:** INV-040 title + body required; slug unique per tenant.
- **Events:** `entry.published`, `entry.edited`.

## Context relationships

```
Activity ──(personal-record.beaten)──▶ Goals
Activity ──(activity.recorded)───────▶ Metrics (load projection)
Metrics  ──(reads planned load)──────▶ Training (read-only provider)
Journal  ──(links)───────────────────▶ Activity (read-only reference)
```
All cross-context writes go through **events + handlers**; all cross-context reads go through **read-only provider ports**. No context imports another's models.

## Seed data (Phase 1 import)

From `course-a-pied/`: athlete profile; `PaceZoneSet` (recovery → intervals) effective 2026-08-20; `TrainingBlock` "Odysséa 10K prep" with cycles C1–C4; the 19/08/2026 test `Activity` (10.01 km, 42:35, 10 splits, best efforts 5k/10k/2mi); `TargetRace` Odysséa Paris 10 km 2026-10-04 (priority A, goal sub-40). The 1-year trail `TargetRace` is added when chosen.
