# Cadence — Roadmap

Living backlog of everything from "one dashboard view" to a complete, self-hosted, 1-year training app. Status legend: ✅ done · 🟡 partial · ⬜ todo.

Today's state: hexagonal socle + `Activity` (record) context + one dashboard page + PWA shell. Everything below is what remains.

---

## 0. App shell & navigation (the missing skeleton)
The dashboard has no chrome yet. This unlocks every other screen.
- ⬜ Persistent **AppLayout**: left sidebar on desktop, bottom tab bar on mobile (PWA).
- ⬜ Nav sections: **Tableau de bord · Historique · Progression · Programme · Allures · Profil**.
- ⬜ Active-route highlighting, page headers, responsive breakpoints, safe-area insets.
- ⬜ Design system: color tokens, typography scale, reusable `Card`/`Stat`/`Button`/`Badge`/`Chart` primitives, empty/loading/error states.
- ⬜ Dark theme polish + accent, icons (lucide), skeletons.

## 1. Auth & profile (replace the dev tenant)
- ⬜ Real authentication (single user): login, session, logout. Replace `FixedTenantContext` with an auth-guard-backed `TenantContext`.
- ⬜ **Profil** screen: name, age, height/weight, history, sport background, goals, editable.
- ⬜ Settings: units, week start, notification prefs, theme.

## 2. Bounded contexts — backend + UI

### 2a. Activity (started)
- ✅ Record activity (aggregate, use case, outbox, endpoint, tests).
- ✅ Dashboard: latest activity (summary, splits chart, best efforts).
- ⬜ **Read side**: list use case + `Historique` screen (paginated, filters by date/type).
- ⬜ Activity **detail** page (full splits, elevation profile, map if GPS later).
- ⬜ **Manual entry form** (React → existing `POST /api/activities`), edit, delete (soft).
- ⬜ Derived metrics: negative/positive split %, pace variability, grade-adjusted pace.

### 2b. Training (the plan) — **Programme**
- ⬜ Aggregates: `TrainingBlock` → `Cycle` (C1–C4) → `PlannedSession`; `PaceZoneSet`.
- ⬜ Use cases: plan block, start/complete cycle, plan session, recalibrate pace zones.
- ⬜ **Programme** screen: macrocycle timeline, current cycle, week view.
- ⬜ **Calendar**: planned vs actual (link an Activity to a PlannedSession).
- ⬜ **Allures** screen: editable pace zones, recalibrated from real sessions.
- ⬜ Seed the real Odysséa plan (C1–C4) from `course-a-pied/`.

### 2c. Metrics (the body)
- ⬜ Aggregates: `DailyMetric` (weight, resting HR, sleep, fatigue, soreness), `Injury`.
- ⬜ Training load: session sRPE, acute (7d) vs chronic (28d), ACWR safe-range flags.
- ⬜ Read model + projection from `activity.recorded` + `metric.logged` (outbox consumer — first real projector).
- ⬜ Metrics screen: weight/sleep/HR/load charts, injury log.

### 2d. Goals & records — **Progression**
- ⬜ Aggregates: `TargetRace` (1-year trail roadmap, priority A/B/C), `PersonalRecord` per distance.
- ⬜ Consume `personal-record.beaten` → update PRs (cross-context handler).
- ⬜ **Progression** screen: PR table, progression curves over time, race countdowns.
- ⬜ Predictors: VDOT / Riegel projections (5k↔10k↔trail), sub-40 tracker.

### 2e. Journal (the story)
- ⬜ `JournalEntry` (markdown, tags, linked activity), feed + article view.
- ⬜ Optional: auto-draft an entry from a recorded activity.

## 3. Outbox delivery (make it real)
- 🟡 Outbox rows are written; ⬜ **PublishOutboxJob** drain (scheduler + queue), consumers/projectors, idempotency, DLQ/`failed_jobs`, retries.
- ⬜ Remove the interim inline publish once the drain lands.

## 4. Integrations
### 4a. Strava (kill manual entry)
- ⬜ OAuth2 connect + encrypted token storage + refresh.
- ⬜ Scheduled sync (activities, splits, best efforts) → `activity.imported`, dedup by `(tenant, source, external_id)`.
- ⬜ Optional webhook subscription for near-real-time.
### 4b. Home Assistant (later)
- ⬜ Rotate the leaked token; push training-load / streak sensors to HA; session-reminder notifications.

## 5. PWA / mobile polish
- 🟡 Manifest + SW + install shell; ⬜ real PNG icons (192/512/maskable), splash, install prompt UX.
- ⬜ Offline strategy (cache API reads, queue writes), background sync.
- ⬜ **Push notifications**: session reminders, "log your metrics", race countdown (Android/Chrome).

## 6. Platform / DevOps
- ⬜ **Laravel Sail** dev environment (one-command up, node for Vite).
- ⬜ Production image: **FrankenPHP** (Octane), migrations on deploy, queue worker + scheduler process.
- ⬜ `docker-compose` on the Raspberry Pi alongside Home Assistant; SQLite volume.
- ⬜ **Tailscale** private access + HTTPS (Tailscale Serve).
- ⬜ Automated SQLite backups (+ off-box copy).
- ⬜ **CI** (GitHub Actions): run `stan` + `test:arch` + `test` + `vite build` on push.

## 7. Quality & hardening (continuous)
- ✅ PHPStan lvl 8, Pest unit/feature/arch, adversarial review pipeline.
- ⬜ Rate limiting on write endpoints, proper error pages, 404/500 Inertia pages.
- ⬜ Per-context `CLAUDE.md` docs (Scribe), keep arch tests growing per context.
- ⬜ Optional: mutation testing (Infection), Larastan → level max with baseline, i18n coverage (fr/en).
- ⬜ Optional: Filament `/admin` for power data management.

---

## Suggested sequencing (each a fleet cycle: test → domain → app → infra → review)
1. **App shell + navigation** (visible structure for everything). ← recommended next
2. **Activity read-side**: Historique + detail + manual entry form.
3. **Training context**: digitize the Odysséa plan → Programme + Allures + calendar.
4. **Auth + Profil** (retire the dev tenant).
5. **Metrics + training load** (first real outbox projector).
6. **Goals + Progression** (PRs, curves, sub-40 tracker).
7. **Strava import**.
8. **Deploy**: Sail → Pi + Tailscale + backups + CI.
9. **Journal**, HA integration, push notifications, polish.

## "v1 usable daily" = 1 + 2 + 3 + 4
Everything after is enrichment. The 1-year trail arc lives in **Goals** (step 6) once the target race is chosen.
