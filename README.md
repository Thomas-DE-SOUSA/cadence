# Cadence

**Your training, from the road to the trail — and the gym.** Cadence is a self-hosted, installable **PWA** to plan and log both your **endurance training** (running: from a 10 km block to a one-year trail objective) and your **strength training** (muscu). One private app, two worlds, an AI coach in each.

> Built for a single athlete, self-hosted on a small server and installed as an app on the phone (private access over [Tailscale](https://tailscale.com/)). No account walls, no data leaving your box.

- 🏃 **Course** — the running world: activities, programs & cycles, paces, readiness, an AI advisor.
- 🏋️ **Muscu** — the strength world: agenda, session runner, per-exercise history, nutrition, bodyweight, an AI check-in.
- 📱 **PWA** — installs on Android/iOS, works like a native app, survives reloads mid-session.
- 🤖 **AI-assisted** — cycle planning, coaching, Strava import and nutrition macro estimation.

<!-- Add screenshots here once you have them:
<p align="center">
  <img src="docs/screenshots/muscu-session.png" width="30%">
  <img src="docs/screenshots/agenda.png" width="30%">
  <img src="docs/screenshots/progression.png" width="30%">
</p>
-->

---

## Table of contents

- [What is Cadence?](#what-is-cadence)
- [Features](#features)
  - [🏃 Course — the running world](#-course--the-running-world)
  - [🏋️ Muscu — the strength world](#️-muscu--the-strength-world)
  - [🤖 AI features](#-ai-features)
- [Tech stack](#tech-stack)
- [Architecture](#architecture)
- [Getting started](#getting-started)
- [Commands](#commands)
- [Configuration](#configuration)
- [Deployment](#deployment)
- [Project structure](#project-structure)
- [License](#license)

---

## What is Cadence?

Most training apps do one thing. Cadence is the personal training hub for someone who **runs and lifts**: it keeps the two disciplines in a single, coherent app so the whole week — long runs, quality sessions, gym days, rest, nutrition, bodyweight — lives in one place.

It's **self-hosted and private by design**: a single Docker container on a small VM / Raspberry Pi, reached over a private Tailscale network, installed as a PWA on your phone. Your data stays yours.

The UI is in **French**; the codebase, identifiers and docs are in **English**.

---

## Features

The app is split into two "worlds" you switch between from a segmented toggle in the top bar. Each world has its own navigation and its own AI coach.

### 🏃 Course — the running world

| Area | What it does |
|---|---|
| **Tableau de bord** (dashboard) | Home for the running side — upcoming work and a countdown to your race objective. |
| **Activités** | Log runs manually, or **import from Strava** by pasting the activity text or a screenshot — the AI parses distance, time, splits and elevation. Per-activity detail with splits, paces and best efforts. |
| **Programme** | Build a season toward a race: a **roadmap** of training **cycles** the AI generates from your objective, level and constraints; regenerate cycle-by-cycle as you progress. |
| **Progression** | Trends over time — volume, paces and key metrics. |
| **Forme** (readiness) | A quick daily **wellness check-in** to track how recovered you are. |
| **Allures** (paces) | Your reference training paces. |
| **Conseil** (advisor) | A conversational **AI running advisor** grounded in your data. |

### 🏋️ Muscu — the strength world

| Area | What it does |
|---|---|
| **Agenda** | A weekly calendar. Drop your saved sessions onto any day; each day is a clear header band, each session a tappable row showing its **duration** once done. |
| **Séances** (templates) | Reusable session models (Push, Legs, Upper A…). Compose exercises, sets, supersets and warm-ups once, then place them on the agenda as many times as you want. |
| **Session runner** | A flat, distraction-free live view to run a session: tick each set, per-set **"previous time"** reference, a **rest chrono** that restarts on every validated set, a **total-session timer** that runs from start to finish, supersets, warm-up sets, add/remove sets on the fly. Auto-saves to `localStorage`, so a reload, an accidental back or the PWA being killed never loses your workout. |
| **Per-exercise history** | Tap an exercise mid-session to open its full history: a **progression chart** (heaviest weight / best e1RM / reps / volume — adaptive to bodyweight moves), **personal records**, and every past session with date, its **position in that session** (order rotates weekly), and all sets. |
| **Progression** | e1RM curves per exercise, weekly volume, muscle balance and records. |
| **Poids** (bodyweight) | Log your weight and see the weekly-average trend. |
| **Nutrition** | Describe a meal in plain French; the **AI estimates calories and macros** and tallies the day against lean-bulk targets. Estimation runs in the background so logging is instant. |
| **Bilan** (check-in) | A conversational **AI strength coach** that reviews your week. |

### 🤖 AI features

Cadence uses LLMs for the fuzzy, language-heavy tasks — never for the numbers it can compute itself (e1RM, volumes, paces are deterministic domain code).

- **Coaching & advice** — the running advisor (*Conseil*) and the strength check-in (*Bilan*) stream responses grounded in your training data.
- **Cycle planning** — generates training cycles for a running program from your objective and constraints.
- **Strava import** — parses a pasted activity (text or screenshot) into a structured activity.
- **Nutrition estimation** — turns a free-text meal into calories + macros, with strict JSON output.

Providers are pluggable behind ports. Out of the box: **Google Gemini** for coaching / planning / Strava import, and **Anthropic Claude** for nutrition estimation. Both are optional — the rest of the app works without any key.

---

## Tech stack

- **Backend:** Laravel 13 · PHP 8.3+ · Eloquent · **SQLite**
- **Frontend:** Inertia · React 19 · TypeScript · Vite · Tailwind CSS 4 — shipped as a **PWA**
- **Admin:** Filament (`/admin`) for fast data management
- **Tests & quality:** Pest (unit / feature / architecture) · PHPStan (Larastan, level 8, strict)
- **Dev:** Laravel Sail via [OrbStack](https://orbstack.dev/)
- **Prod:** a single Docker image (**FrankenPHP**) — SQLite on a mounted volume, exposed privately over Tailscale

---

## Architecture

Cadence is a **modular monolith** built with **Hexagonal Architecture + DDD + Clean Architecture**, **test-first (TDD)**.

- Business code lives under `src/` (PSR-4 `Cadence\ → src/`), split by **bounded context**, each with `Domain / Application / Infrastructure`.
- `app/` holds only Laravel glue (providers, HTTP kernel, etc.).
- The domain layer is pure PHP with zero framework coupling; use cases are thin orchestrators; infrastructure holds Eloquent, controllers, jobs and the outbox.
- Everything is tenant-scoped, and there are no static `now()`/`uuid()`/`random` calls in business code — `Clock` / `IdGenerator` / `RandomGenerator` are injected for determinism.

**Bounded contexts** (`src/`): `Activity`, `Training`, `Coaching`, `Strength`, `Athlete`, `Shared`.

The full doctrine lives in [`docs/architecture/`](docs/architecture):

| Doc | Covers |
|---|---|
| `00-core.md` | Golden rules, layers, bounded contexts, naming, determinism |
| `10-backend.md` | Domain / application / infrastructure / persistence / outbox |
| `20-frontend.md` | Inertia/React clean architecture (Container/View, gateways, Result) |
| `30-testing.md` | Pest TDD, fakes-only, architecture tests |
| `40-review.md` | Review dimensions & severities |
| `50-domain-map.md` | Bounded contexts, aggregates, invariants, events |

Architecture is enforced by Pest **architecture tests** (`composer test:arch`) — domain never depends on the framework, application never touches Eloquent, etc.

---

## Getting started

### Requirements

- **PHP 8.3+**, **Composer**, and **Node.js 22+** — or just **Docker** + [OrbStack](https://orbstack.dev/) if you prefer Sail.
- SQLite (bundled with PHP — no separate database server to install).

### Quick start (local PHP)

```bash
git clone git@github.com:Thomas-DE-SOUSA/cadence.git
cd cadence

# Install deps, create .env, generate the key, run migrations, build assets
composer setup

# Run everything (Laravel server + Vite + queue + logs) in one command
composer dev
```

Then open the URL printed by the dev server (default **http://localhost:8000**).

### Quick start (Docker / Sail via OrbStack)

```bash
git clone git@github.com:Thomas-DE-SOUSA/cadence.git
cd cadence
cp .env.example .env

./vendor/bin/sail up -d          # start the containers
./vendor/bin/sail composer setup
./vendor/bin/sail npm run dev    # Vite dev server (or `npm run build` for a one-off)
```

> First run: `composer setup` creates the SQLite file and runs migrations. The admin panel is at **/admin** (Filament).

---

## Commands

```bash
composer dev          # one-command dev runner (server + Vite + queue + logs)
composer setup        # install + .env + key + migrate + build (first-time setup)

composer test         # Pest unit + feature tests
composer test:arch    # Pest architecture tests (layer boundaries)
composer stan         # PHPStan / Larastan (level 8, strict)

npm run dev           # Vite dev server (HMR)
npm run build         # production asset build
```

The quality gate for any change is: `composer stan && composer test:arch && composer test`.

---

## Configuration

Copy `.env.example` to `.env` (done automatically by `composer setup`). The app runs on SQLite with sensible defaults out of the box.

**AI keys (optional — enable the AI features):**

```dotenv
# Running advisor, strength check-in, cycle planning, Strava import
GEMINI_API_KEY=
GEMINI_MODEL=gemini-3.6-flash

# Nutrition macro estimation
ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-haiku-4-5
```

Without these keys the app runs fine; only the AI-powered screens degrade gracefully (they surface a clear "unavailable, try again" state instead of crashing).

---

## Deployment

Production is a **single Docker image** built with a multi-stage `Dockerfile` (a Node stage builds the Vite/PWA assets, a FrankenPHP stage serves the app). It runs the migrations on boot and serves over HTTP; SQLite lives on a mounted volume.

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

Exposed privately with `tailscale serve` — no public port, no auth server needed for a single-user private deployment. Install the served URL as a PWA on your phone (Add to Home Screen).

---

## Project structure

```
cadence/
├── app/                       # Laravel glue only (providers, HTTP kernel…)
├── src/                       # Business code — PSR-4 Cadence\ → src/
│   ├── Activity/              #   running activities + Strava import
│   ├── Training/              #   programs, cycles, roadmap
│   ├── Coaching/              #   AI advisor / check-in (streaming)
│   ├── Strength/              #   muscu: sessions, templates, nutrition, weight…
│   ├── Athlete/               #   athlete profile
│   └── Shared/                #   Clock, IdGenerator, AI clients, tenant context…
│       (each context → Domain / Application / Infrastructure)
├── resources/js/              # Inertia + React + TypeScript (PWA frontend)
│   ├── pages/                 #   one file per screen
│   ├── muscu/                 #   strength-specific components
│   └── components/, layouts/, lib/
├── docs/architecture/         # the doctrine (read before contributing)
├── database/migrations/       # schema
└── docker-compose.prod.yml    # single-container production
```

---

## License

This started as a personal project. If you'd like to reuse or build on it, open an issue — a proper open-source license will be added. Until then, treat it as source-available for learning, not for redistribution.

---

<sub>Made for training that spans the road, the trail and the rack. 🏃🏋️</sub>
