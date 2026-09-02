# Feature — Bilan hebdo cross-modal (coach muscu + course)

**But.** Un coach qui juge la/les semaine(s) de l'athlète en combinant **course** (charge, forme, 80/20, compliance, readiness) et **muscu** (fréquence, tonnage, équilibre musculaire, poids). Verdict IA streamé + conversation + propositions.

## Décisions (verrouillées)
- **Moteur IA : Gemini** (`gemini-3.6-flash`), on réutilise `GeminiClient` / le pattern `GeminiCoachStreamer`. Pas de Claude.
- **Périmètre : complet** (verdict streamé + conversationnel + verdict structuré + propositions + persistance).
- **Placement : monde muscu** (nav muscu → `/muscu/bilan`).
- **Ajustements muscu = conseils textuels** au début (la muscu n'a pas de programme planifié à muter comme la course ; la course garde `propose_session_change` → `SessionAdjuster`).

## Ce qui existe déjà (à réutiliser tel quel)
- Course : `TrainingLoadCalculator` (ACWR, CTL/ATL/TSB, rTSS, 80/20), `FitnessAssessor` (VDOT/allures), `AdaptationAnalyzer` (verdict déterministe), `ReadinessAssessor` (check-in Forme), `AthleteBrief`/`AdaptationView`/`TrainingLoadView`.
- Muscu : `StrengthView::weekly|volumeByMuscle|records|progression`, `WeightView::weeklyAverages`.
- IA : ports `CoachStreamer`/`CoachChat`, `GeminiCoachStreamer`, `CoachRequestBuilder`, `CoachingKnowledge` (doctrine), `Conversation` (persistance JSON), contrôleurs SSE, `CoachTurnService`.
- Front : pattern SSE (`Advisor.tsx`/`CoachThread.tsx`), renderer Markdown, `Card`, `AppLayout`, nav muscu.

## À construire

### Domaine (`src/Coaching/Domain`)
- `ValueObject/StrengthWeekSummary` — VO muscu hebdo : sessions, tonnageKg, workingSets, séances jambes, équilibre par muscle (map), tendance vs semaine précédente, jours depuis dernière séance.
- `ValueObject/WeeklyReviewContext` — contexte cross-modal : semaine (start/end), bloc course (réutilise fitness + charge + verdict `AdaptationReport`), `StrengthWeekSummary`, tendance poids, readiness, objectif/course cible.
- `Port/StrengthContextProvider` — `weekSummary(TenantId, weekStart): StrengthWeekSummary` + `weightTrend(...)`. (implémenté côté infra, cross-context comme `ProgramContextProvider`.)
- `Model/Conversation` — étendre avec un `scope` (`day` | `week`) OU nouvelle agrégat `WeeklyReview`. **Choix : réutiliser `Conversation` avec `sessionDate = weekStart` et un `scope`** pour limiter la surface (migration légère).
- `Service/CrossModalAnalyzer` (optionnel Phase 2) — combine verdict course + charge muscu → note globale + red flags cross-modaux (ex. muscu jambes lourde la veille d'une séance qualité).

### Application (`src/Coaching/Application`)
- `WeeklyReview/WeeklyReviewService` — calqué sur `CoachTurnService` : `start(weekStart, text)` assemble `WeeklyReviewContext` (course + muscu + poids + readiness), enregistre le message athlète ; `finish(...)` enregistre la réponse.
- DTO `WeeklyTurn` (conversationId, context, history).

### Infrastructure (`src/Coaching/Infrastructure`)
- `Read/StrengthWeeklyBrief` — construit `StrengthWeekSummary` depuis `StrengthSessionRepository::forTenant` + `WeightEntryRepository` + catalogue exercices. **Pur, testable.**
- `Provider/StrengthWeeklyContextProvider` — implémente `StrengthContextProvider` (bridge vers le contexte Strength).
- `Ai/WeeklyCoachRequestBuilder` — prompt cross-modal : doctrine + `methods/strength-and-running.md`, bloc course, bloc muscu, bloc poids/readiness, règles ; tool `advise_next_week` (structuré : verdict, priorités course/muscu) + `propose_session_change` (course).
- `Ai/GeminiCoachStreamer` — réutilisé (le port prend un builder ; on injecte le weekly builder via un 2e binding ou un paramètre). **Choix : un `WeeklyCoachStreamer` qui réutilise `GeminiClient` + `WeeklyCoachRequestBuilder`.**
- `Http/Controller/ShowWeeklyReviewController` — page Inertia `MuscuBilan` avec les chiffres pré-calculés.
- `Http/Controller/StreamWeeklyReviewController` — SSE (copie de `StreamCoachController`).
- `Http/Controller/ShowWeeklyThreadController` — historique (comme `ShowCoachThreadController`).
- `Knowledge/methods/strength-and-running.md` — doctrine interférence concurrent-training.
- Routes dans `CoachingServiceProvider` (préfixe `muscu`, ou groupe dédié).

### Persistance
- Migration : ajouter `scope` (string, défaut `day`) à `conversations` (nullable/indexé). `program_id`/`cycle_id` nullable pour le scope `week` (bilan pas rattaché à un programme).

### Frontend (`resources/js`)
- `pages/MuscuBilan.tsx` — chiffres (course + muscu + poids) + bouton « Générer le bilan » (SSE) + fil conversationnel (réutilise le pattern `CoachThread`). Markdown.
- `lib/nav.ts` — entrée `{ label: 'Bilan', href: '/muscu/bilan', icon: Sparkles }` dans `muscuNavItems`.

### Tests (Pest)
- Unit : `StrengthWeeklyBrief` (agrégation hebdo, équilibre, tendance), VO guards.
- Feature : page `/muscu/bilan` rend les props ; le fil se persiste ; SSE renvoie des events (fake streamer).
- Arch : les nouvelles couches respectent les frontières (fake `StrengthContextProvider` en test).

## Ordre de build (incréments testés, gate `stan && test:arch && test`)
1. **Fondation muscu** : `StrengthWeekSummary` VO + `StrengthWeeklyBrief` read + unit tests. *(déployable : rien d'user-facing encore)*
2. **Contexte + provider** : `WeeklyReviewContext`, `StrengthContextProvider` port + adapter, wiring.
3. **Prompt + streamer** : `WeeklyCoachRequestBuilder`, `WeeklyCoachStreamer`, doctrine md.
4. **Persistance** : migration `scope`, `Conversation` étendu, `WeeklyReviewService`.
5. **HTTP + page (MVP verdict)** : controllers + `MuscuBilan.tsx` + nav → **1er milestone déployé**.
6. **Conversationnel + propositions** : fil de messages, tool structuré, application des propositions course.

## Notes
- Tout le code/identifiants/commentaires en anglais (user-facing en français). TDD. Gate à chaque étape.
- Semaine = lundi→dimanche (cohérent avec `WeightView` et l'ISO week course).
- « la/les semaines » : le bilan cible la **dernière semaine complète** + un mini-contexte de tendance (3-4 semaines) ; navigation semaine possible en Phase 2.
