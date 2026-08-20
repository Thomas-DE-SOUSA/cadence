# Frontend Doctrine (Inertia + React + TypeScript)

Load `00-core.md` first. Transposed from Malta's core-frontend + layers/module/container-view/decision knowledge. Malta uses Next.js server actions; **Cadence uses Inertia**, so the "server action" role collapses into the Laravel controller — but the client-side clean-architecture split is preserved.

## 1. Layered client architecture (dependencies point down)

```
(0) contract types (generated from PHP data objects)
(1) infrastructure   → gateways / HTTP (Inertia router, axios)
(2) domain           → framework-agnostic business types + pure functions
(3) application      → use cases returning Result<T,E>
(4) view-models      → presenters mapping application output → view props
(5) hooks            → React glue (Inertia useForm, react-query, state)
(6) components       → Container + View
```
Business logic (2–3) is framework-agnostic and **never** lives inside a React component. Components are thin delegators.

## 2. Result pattern (never throw in business logic)

```ts
type Result<T, E> = { success: true; value: T } | { success: false; error: E };
```
Every client use case and gateway returns a `Result`. UI branches on it; it never `try/catch`es business flow. Error variants carry an `ErrorCode` (same enum as the backend).

## 3. Container / View (MANDATORY)

- **View** = pure. Props only. **Zero** hooks (except `useForm`/animation helpers), no `t()`, no router, no data fetching. Translations arrive via a `translations` prop. Storybook-renderable with no mocks. ~≤80 lines.
- **Container** = wires hook → view, builds the `translations` object, contains **zero business JSX**. ~60–80 lines.
- One feature = `{Feature}Container.tsx` + `{Feature}View.tsx`.

## 4. Decision pattern (3+ outcomes)

- Pure `resolve{Feature}Decision(result)` in `domain/` → returns a **discriminated union** (no deps, no side effects).
- A framework executor hook performs the effect (router redirect, toast, storage).
- An orchestrator hook injects both. Keeps branching testable and out of components.

## 5. Module layout (feature-first)

```
resources/js/features/{feature}/
  domain/          # pure types + decisions (imports nothing)
  application/     # client use cases (imports domain only)
  infrastructure/  # gateways (imports domain + application)
  hooks/
  components/      # Container + View
  schema/          # Zod schemas (error messages = i18n keys)
  i18n/            # en + fr namespaces (namespace = feature path)
```
Layer imports enforced: `domain` imports nothing, `application` imports only `domain`, `infrastructure` imports both. Cross-feature imports only through a feature's public barrel.

## 6. State & data

- **Server state:** Inertia props (server-driven) + optionally React Query for polling widgets. **Never** hold server state in Zustand.
- **Forms:** Inertia `useForm` (or react-hook-form) + Zod. Schemas in separate files; memoized factories `(t) => z.object(...)`; validation messages are i18n keys.
- **Global UI state only:** Zustand. Never React Query for UI state.
- Avoid `useEffect` for syncing derived state — derive during render or in a selector.

## 7. Design system & UX

- Search before creating: reuse existing components/barrels before adding a new one.
- Icons: `lucide-react`. Loading: skeletons, not spinners. Every string through i18n (en + fr).
- Server components mindset: keep pages lean; ship interactivity only where needed.
- Views are Storybook-ready and accessible (labels, focus, contrast).

## 8. Frontend definition of done

- [ ] No business logic in a component; Container/View split respected.
- [ ] All client use cases return `Result`; no thrown business errors.
- [ ] Strings i18n'd (en+fr); Zod messages are keys.
- [ ] Types generated from PHP data objects (not hand-duplicated).
- [ ] Views render in Storybook without mocks.
