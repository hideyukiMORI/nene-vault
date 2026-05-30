# Frontend Standards

NeNe Vault's admin UI is a **React + TypeScript** client of the JSON API. It is
**not** the source of truth for schema, validation, or persistence — the backend
(`composer check`, OpenAPI) is.

**Status:** **Phase 2** — standards locked here; the scaffold is rebuilt to comply
(Issue #34). The PR #31 scaffold is **non-compliant** and is being replaced.

**Framework baseline:** [NENE2 frontend integration](https://github.com/hideyukiMORI/NENE2/blob/main/docs/development/frontend-integration.md).
**Inheritance map:** `docs/inheritance-from-nene2.md`.

**Enforcement level:** violations of placement, dependency direction, data flow,
security, styling, or testing rules in this document **block merge to `main`**. No
temporary exceptions without an ADR.

> This document mirrors the NeNe Records frontend standards, **adapted for vault**:
> i18n is **ja/en only** (ADR 0005) and reads the repository-root `locales/`
> (single source of truth); entities are vault resources; Problem Details base is
> `https://nene-vault.dev/problems/`; auth tokens are **not** stored in
> `localStorage`.

---

## Principles

| Principle | Meaning |
| --- | --- |
| **API first** | OpenAPI (`docs/openapi/openapi.yaml`) is the contract; UI reflects API types and errors, never replaces validation. |
| **Unidirectional flow** | Data flows down (API → entity → feature → UI); events flow up (UI → feature hook → mutation → API). No sideways shortcuts. |
| **Strict TypeScript** | `strict` + extra compiler guards; no untyped escape hatches. |
| **Fixed placement** | Models, enums, hooks, tests live in mandated paths — placement violations block merge. |
| **Explicit dependencies** | Import graph encodes architecture; ESLint enforces it. |
| **Loose coupling** | Layers communicate through public surfaces (`index.ts`, props, hooks) — not internals. |
| **Secure by default** | Fail closed on auth errors; minimal trust of client input and third-party markup; no token in `localStorage`. |
| **Test by behavior** | Tests assert user-observable outcomes; MSW mirrors OpenAPI at boundaries. |
| **Theme by substitution** | All visual values live in theme token files; swapping the active theme restyles the app without touching components. |
| **No magic styling** | Margin, padding, color, typography, background never appear as raw literals outside the theme layer. |
| **Locale single source** | UI strings come from repo-root `locales/ja.json` + `en.json` (ADR 0005); never duplicated in components. |

---

## Stack

| Layer | Choice | Notes |
| --- | --- | --- |
| UI | **React 18** | Function components + hooks only — no class components |
| Language | **TypeScript** (latest stable) | All app source in `.ts` / `.tsx` |
| Bundler | **Vite** | Dev server + production build |
| Package manager | **npm** | Commit `frontend/package-lock.json`; CI uses `npm ci` |
| Routing | **React Router** | URL is shareable state |
| Server state | **TanStack Query v5** | Queries, mutations, cache, invalidation |
| Forms | **React Hook Form** + **Zod** | Client UX validation only — API authoritative |
| Lint | **ESLint** flat config: `typescript-eslint` strict-type-checked, `react-hooks`, `jsx-a11y`, import boundaries | `--max-warnings 0` |
| Format | **Prettier** | Single formatter |
| Unit / integration | **Vitest** + **Testing Library** + **MSW** | jsdom |
| Dead code | **knip** | Fail CI on unused exports in `entities/`, `features/` |
| Styling | **Tailwind CSS v4** | Semantic utilities mapped to CSS custom properties via `@theme` |
| Design tokens | **CSS custom properties** in `shared/ui/theme/` | Single source of truth for visual values |
| Component catalog | **Storybook** (React + Vite) | Required for `shared/ui` primitives; In/Out contracts |
| API types | **openapi-typescript** | Generate from `docs/openapi/openapi.yaml` |

Alternate UI frameworks, state libraries, CSS approaches, or package managers
require an ADR. Do not mix Tailwind with CSS Modules or CSS-in-JS without an ADR.

---

## Architecture (strict layered, FSD-adjacent)

`app → pages → features → entities → shared`. Stricter than generic FSD: entity
modules and API boundaries are vault-specific.

### Layer responsibilities

| Layer | Owns | Must not own |
| --- | --- | --- |
| **`shared/`** | Transport, design tokens, i18n, pure utils, env | Routes, features, resource models, workflows |
| **`entities/`** | One API resource: DTO mapping, query keys, TanStack hooks | JSX, cross-resource orchestration, feature copy |
| **`features/`** | User workflows composing entities + UI | Raw HTTP, DTO types, direct TanStack key strings |
| **`pages/`** | Route wiring, lazy loading, layout slots | Business rules, API calls |
| **`app/`** | Providers, router, error boundary, auth gate | Feature-specific screens |

### Dependency graph — no arrow points upward

```
app → pages → features → entities → shared/api → API
                 ↓            ↓
            shared/ui     shared/lib
```

Hard rule: `entities → features`, `shared → entities`, sibling `features → features`,
and sibling `entities → entities` are **forbidden** and ESLint-enforced.

### Import matrix (mandatory)

| From ↓ / To → | `shared/ui` | `shared/api` | `shared/lib` | `shared/i18n` | `entities/*` | `features/*` | `pages/*` | `app/*` |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `shared/ui` | ✓ | ✗ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ |
| `shared/api` | ✗ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |
| `entities/{r}` | ✗ | ✓ client only | ✓ | ✓ | ✗ sibling | ✗ | ✗ | ✗ |
| `features/{f}` | ✓ | ✗ | ✓ | ✓ | ✓ via `index.ts` | ✗ cross-feature | ✗ | ✗ |
| `pages/` | ✓ | ✗ | ✓ | ✓ | ✗ direct | ✓ via `index.ts` | ✗ | ✗ |
| `app/` | ✓ | ✓ providers | ✓ | ✓ | ✗ | ✗ | ✓ | ✓ |

### Public surfaces

Every `entities/{resource}/` and `features/{feature}/` exposes **`index.ts` only**.
Internals (`mapper.ts`, `api-types.ts`, `ui/*.tsx`) are private. `index.ts` does
**not** re-export `api-types`, `mapper`, or generated DTOs.

---

## Repository layout

```text
frontend/
  package.json  package-lock.json
  tsconfig.json  tsconfig.app.json  tsconfig.node.json
  vite.config.ts  vitest.config.ts  eslint.config.js
  knip.json  .storybook/
  README.md                         # links to this document
  src/
    app/
      providers.tsx                 # QueryClientProvider, Router, theme, i18n
      router.tsx
      root-error-boundary.tsx
      auth-gate.tsx                 # fail-closed session check
    pages/
    features/
    entities/                       # document, organization, user, vault-settings, audit-event, export
    shared/
      ui/
        theme/                      # tokens — ONLY place for raw visual values
          index.css  active.css  themes/default.css  tokens.ts
        primitives/                 # Button, Input, Text, Stack, …
        components/                 # Dialog, ConfirmDialog, EmptyState, DataTable shell
        index.ts
      api/
        client.ts                   # fetch transport, auth, status parse
        errors.ts                   # Problem Details → AppError
        generated/                  # openapi-typescript output
      i18n/                         # ja/en, reads repo-root locales/
      lib/
      config/                       # env.ts (Zod-validated)
  tests/
    setup/  msw/  factories/  render/
```

---

## Type and module placement (zero tolerance)

Placement violations are never accepted on `main`. ESLint import boundaries fail
`npm run lint`; reviewers reject structural drift.

### Canonical entity tree

```text
entities/vault-document/
  index.ts          # ONLY public surface
  ids.ts            # branded IDs (VaultDocumentId)
  enum.ts           # resource-scoped enums (status, category, source)
  api-types.ts      # DTOs (aliases of generated/ post-codegen)
  model.ts          # UI read models
  mapper.ts         # DTO ↔ model (pure, unit-tested)
  query-keys.ts     # TanStack key factory
  queries.ts        # useQuery hooks
  mutations.ts      # useMutation hooks
  mapper.test.ts
```

### Forbidden placements (automatic reject)

- DTOs / API shapes in `features/`, `pages/`, `shared/ui/`, or `.tsx` (except `*Props`)
- Models, enums, mappers outside `entities/{resource}/`
- TanStack logic outside `query-keys.ts` / `queries.ts` / `mutations.ts`
- `fetch` outside `shared/api/client.ts`
- `shared/api/generated/` imported from any `.tsx` or from `features/`
- Deep entity imports from features (must go through `index.ts`)
- Root-level `src/types/`, `src/utils/` dumps

---

## Data flow

### Read path

```
API JSON → shared/api/client.ts → entities/{r}/api-types.ts
  → entities/{r}/mapper.ts → entities/{r}/queries.ts (TanStack cache)
  → features/{f}/hooks → features/{f}/ui (render props)
```

Mappers run **inside entity hooks**, not components. Components receive **model**
types and callbacks — never `Response`, never DTOs. Stable query keys from
`query-keys.ts` only.

### Write path

```
UI event → entities/{r}/mutations.ts (useMutation) → shared/api/client.ts → API
  → onSuccess: invalidate query-keys (explicit) → onError: Problem Details → AppError → UI
```

Mutations live in `mutations.ts`; features call exported hooks, not inline
`useMutation`. Optimistic updates require rollback + a test proving it.

### URL and shareable state

| State | Location |
| --- | --- |
| Resource id in detail view | route param (`/documents/:id`) |
| Filters, sort, page | `searchParams` (serializable) |
| Modal open, tab | local `useState` in feature |
| Server data | TanStack Query cache — not duplicated in a store |

### Four explicit UI states

Every data screen implements **Loading / Empty / Error / Success** — no blank
pages, no ambiguous combined flags. Error shows a safe message + retry; Problem
Details `type` logged in dev only.

---

## TypeScript strictness

`tsconfig.app.json` minimum:

```jsonc
{
  "compilerOptions": {
    "strict": true,
    "noUncheckedIndexedAccess": true,
    "noImplicitOverride": true,
    "exactOptionalPropertyTypes": true,
    "verbatimModuleSyntax": true,
    "moduleResolution": "bundler",
    "jsx": "react-jsx",
    "noEmit": true,
    "isolatedModules": true,
    "noUnusedLocals": true,
    "noUnusedParameters": true,
    "noFallthroughCasesInSwitch": true,
    "forceConsistentCasingInFileNames": true
  }
}
```

Hard rules: `any` forbidden (use `unknown` + narrow); `@ts-expect-error` needs an
Issue/ADR id; no `!` non-null assertion without an invariant comment; `interface`
for component props, `type` for unions; `satisfies` for const config; branded IDs
in `ids.ts` (no bare `string` for resource ids); exhaustive `switch` on unions;
env vars validated once in `shared/config/env.ts` (Zod). **No default exports.**

---

## Design system and theming (zero tolerance)

All visual values live in `shared/ui/theme/`. Components and features **never**
hard-code margin, padding, color, font, background, radius, shadow, or z-index.

- One file per complete theme: `theme/themes/{name}.css` with **all** tokens.
- `theme/active.css` is a single pointer: `@import './themes/default.css';`.
- Switch theme by changing only that import — no component PR.
- `app/providers.tsx` imports `theme/index.css` once; features/pages never import theme CSS.
- Components use **Tailwind semantic utilities only** (`bg-surface`, `text-primary`,
  `p-inline-md`). **No arbitrary values** (`p-[13px]`, `text-[#fff]`), **no hex/rgb/px
  literals** in `.tsx`/`.ts`, **no inline `style`** with design literals.

Required token categories per theme file: Color, Spacing, Typography, Background,
Radius, Shadow, Border, Motion, Z-index — semantic names (`text-primary`, not
`blue-500`).

`shared/ui` layering: `theme/` (no React) → `primitives/` → `components/` →
`index.ts`. Features compose from the `shared/ui` barrel only — never deep paths.

---

## Storybook and component contracts

Storybook is **required** for `shared/ui` primitives and composed components.
Stories under `features/`, `pages/`, `entities/` are **forbidden**.

Each story documents the boundary at the top:

```typescript
/**
 * Button — primary action control.
 * In:  variant, size, disabled, type, children
 * Out: onClick(event)
 * Does not: fetch data, know entity ids, read router/query cache.
 */
```

Cover at least default, disabled (if applicable), and each variant. Colocate
`Button.tsx` + `Button.stories.tsx`.

---

## API and data access

### HTTP client (`shared/api/client.ts`)

- Single `apiClient` with typed `get/post/patch/delete`.
- Attaches auth per policy (see Security — **not** a `localStorage` bearer).
- Parses JSON; throws **`AppError`** from Problem Details on 4xx/5xx.
- Transport only — no domain logic.

### TanStack Query

Defaults in `app/providers.tsx` (`staleTime`, retry on retryable `AppError` only,
`refetchOnWindowFocus: import.meta.env.PROD`, mutations no retry). Per entity:
hooks with explicit return types (`UseQueryResult<VaultDocument, AppError>`);
`queryFn` calls the mapper before caching.

### Generated types

`openapi-typescript` writes `shared/api/generated/`; regenerate when
`docs/openapi/openapi.yaml` changes (`npm run codegen`). `entities/{r}/api-types.ts`
aliases generated types. Features consume **model** via `index.ts` — never raw DTOs.

---

## State management

| State | Tool | Location |
| --- | --- | --- |
| Remote server data | TanStack Query | `entities/*/queries.ts` |
| Writes | TanStack mutations | `entities/*/mutations.ts` |
| URL / shareable | React Router | `pages/` + feature hooks reading `searchParams` |
| Form draft | React Hook Form | feature ui + hooks |
| Ephemeral UI | `useState` | feature ui |
| Auth session flag | Context in `app/` only | minimal; details from API |

No Redux/Zustand/Jotai without an ADR. Do not mirror Query cache into Context.

---

## Internationalisation (ja / en only — ADR 0005)

> vault differs from nene-records here: **only `ja` and `en`**, and the catalogs
> are the **repository-root `locales/ja.json` + `locales/en.json`** (single source
> of truth; `docs/development/locale-guide.md`). No third locale; no parallel
> `messages/*.ts` set.

| Module | Purpose |
| --- | --- |
| `shared/i18n/locales.ts` | `SupportedLocale = 'ja' \| 'en'`, meta (label, dir) |
| `shared/i18n/catalogs.ts` | imports `@locales/ja.json` + `@locales/en.json` |
| `shared/i18n/i18n-context.tsx` | `I18nProvider` — detection, persistence, context |
| `shared/i18n/use-translation.ts` | `useTranslation()` → `t(key, params)` |
| `shared/i18n/translate.ts` | pure `translate()` + dot-notation lookup |
| `shared/i18n/map-problem-details.ts` | `AppError` / problem type → locale `problem.*` key |
| `shared/i18n/test-helpers.tsx` | `renderWithI18n(locale)`, `withI18n(locale)` |

Rules: **no hardcoded user-facing strings** — use `t('document.list.title')`. Keys
follow `docs/development/locale-guide.md` (namespaces: `common`, `auth`,
`navigation`, `document`, …). `ja` is default; detection order
`localStorage['nene-vault.locale']` → `navigator.language` → `ja`. Statutory labels
(取引年月日, 取引金額, 取引先名) stay Japanese in both locales. Missing key renders
the key (visible, not blank). `{{name}}` interpolation. Adding a string updates
**both** `locales/*.json` (enforced by `composer locales`).

---

## Security

The browser is hostile context. Treat rendered user content and client storage as
potentially compromised.

| Topic | Rule |
| --- | --- |
| **Secrets** | Never in repo. Only public `VITE_*` in frontend env. |
| **Auth tokens** | **No JWT/refresh token in `localStorage`** — httpOnly cookie, or an explicit ADR if a bearer-in-memory approach is justified. The PR #31 scaffold violated this; the rebuild fixes it. |
| **XSS** | No `dangerouslySetInnerHTML` without DOMPurify + Issue. |
| **Links** | `rel="noopener noreferrer"` on `target="_blank"`. |
| **Open redirects** | Validate post-login redirect against an allowlist. |
| **Dependencies** | `npm audit` in CI; block high/critical on `main`. |
| **PII in logs** | Never log tokens, passwords, or full Problem Details in production. |
| **RBAC UI** | Hide/disable actions by API-exposed capability — UI gating is UX only; API enforces. |
| **Fail closed** | 401 → login; 403 → forbidden; never silent unauthenticated mutations. |
| **Storage path** | Never construct or display backend storage paths (they are not in API responses anyway). |

---

## Testing

| Level | Tool | Required when |
| --- | --- | --- |
| **Unit** | Vitest | `mapper.ts`, `query-keys.ts`, pure `lib/` — every entity |
| **Integration** | Vitest + Testing Library + MSW | every feature PR |
| **Contract** | MSW vs OpenAPI | endpoint touched |

Placement: mapper/query-key tests colocated in the entity; component tests
`FeatureName.test.tsx`; feature hook tests `use-{feature}.test.ts(x)` in the
feature's `hooks/`; MSW handlers `tests/msw/{resource}.ts`; factories build
**models**; `renderWithProviders` in `tests/render/`.

Rules: query by role/label/accessible name (not class/testid); `userEvent.setup()`;
`createTestQueryClient()` with retries off; MSW shapes match OpenAPI; no mocking
child components; no full-page snapshots; bug fixes include a regression test.

**Required before merge:** every new `use-{feature}` hook ships a colocated hook
test (render against MSW, assert primary query loads + each mutation's observable
outcome). Missing → merge blocked. Feature UI: happy path + primary Problem Details
error path. `npm run test` green.

---

## Accessibility / performance

- **WCAG 2.2 AA**; `eslint-plugin-jsx-a11y` errors fail CI; focus management on
  route/modal change; form errors via `aria-describedby`.
- Route-level code splitting (`React.lazy`); virtualize lists >100 rows (Issue);
  `loading="lazy"` on images.

---

## Commands and CI

```bash
npm install --prefix frontend
npm run dev --prefix frontend       # proxies /admin, /health to :8080
npm run codegen --prefix frontend   # openapi-typescript from docs/openapi/openapi.yaml
npm run check --prefix frontend     # type-check + lint + format + test + knip + build-storybook
```

`npm run check` is the frontend quality gate — must be green before merge, like
`composer check` on the backend.

ESLint encodes boundaries: `features/**` → no `shared/api/generated/**`, no deep
`entities/**` except `index.ts`; `shared/ui/**` → no `entities/`/`features/`;
`entities/*/**` → no sibling `entities/*/**`. Styling: forbid Tailwind arbitrary
values (`/\[.+\]/`) outside `shared/ui/theme/`, forbid inline `style` literals in
`features/`/`pages/`.

---

## Non-goals

- Duplicating API validation as source of truth in the browser.
- Hard-coded visual values outside `shared/ui/theme/`.
- Auth token in `localStorage` (without ADR).
- A third UI locale beyond ja/en.
- DB or MCP access from the browser.
- Committing `node_modules/` or generated assets.
- Alternate UI stack without an ADR.

---

## Related documents

- Self-review: `docs/review/frontend.md`
- Locale: `docs/development/locale-guide.md`
- Naming: `docs/development/naming-conventions.md` (frontend section)
- Backend: `docs/development/coding-standards.md`
- Reference: NeNe Records `docs/development/frontend-standards.md`
