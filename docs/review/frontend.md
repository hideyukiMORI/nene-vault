# Frontend Self-Review Checklist

Use for any change under `frontend/`. Source of truth:
[`docs/development/frontend-standards.md`](../development/frontend-standards.md).
Violations of placement / dependency / data-flow / security / styling / testing
**block merge**.

---

## Architecture & placement (zero tolerance)

- [ ] New code is in the correct layer (`app` / `pages` / `features` / `entities` / `shared`).
- [ ] No upward import (`entities → features`, `shared → entities`, sibling `features → features`, sibling `entities → entities`).
- [ ] Entity slice exposes `index.ts` only; internals (`api-types`, `mapper`, `ui/*`) stay private.
- [ ] `index.ts` does not re-export `api-types`, `mapper`, or generated DTOs.
- [ ] DTOs/models/enums/mappers live in `entities/{resource}/` — not in `features/`, `pages/`, `shared/ui/`, or `.tsx`.
- [ ] `fetch` only in `shared/api/client.ts`.
- [ ] `shared/api/generated/` is not imported from any `.tsx` or from `features/`.

## Data flow

- [ ] Mappers run inside entity hooks, not components.
- [ ] Components receive `model` types + callbacks — never `Response` or DTOs.
- [ ] Query keys come from `query-keys.ts` (no string literals in features).
- [ ] Mutations live in `mutations.ts`; features call exported hooks (no inline `useMutation`).
- [ ] `onSuccess` invalidations are explicit; optimistic updates have rollback + test.
- [ ] Screen implements all four states: Loading / Empty / Error / Success.

## TypeScript

- [ ] No `any` (use `unknown` + narrow).
- [ ] No `@ts-expect-error` / `@ts-ignore` without an Issue/ADR id.
- [ ] No `!` non-null assertion without an invariant comment.
- [ ] Branded IDs from `ids.ts` for resource ids (no bare `string` across layers).
- [ ] `interface` for props, `type` for unions; `satisfies` for const config.
- [ ] **No default exports.**
- [ ] Exhaustive `switch` on discriminated unions.

## Styling / theme (zero tolerance)

- [ ] No hex/rgb/hsl/px literals in `.tsx`/`.ts`.
- [ ] No Tailwind arbitrary values (`p-[13px]`, `text-[#fff]`) outside `shared/ui/theme/`.
- [ ] No inline `style={{…}}` with design literals.
- [ ] New visual values added to `shared/ui/theme/themes/*.css` as semantic tokens.
- [ ] Features compose from the `shared/ui` barrel — no deep primitive paths.
- [ ] Theme still swappable by changing only `theme/active.css`.

## i18n (ja/en only — ADR 0005)

- [ ] No hardcoded operator-facing strings — all via `t(key)`.
- [ ] New keys added to **both** `locales/ja.json` and `locales/en.json` (`composer locales` green).
- [ ] Statutory labels (取引年月日, 取引金額, 取引先名) stay Japanese in both locales.
- [ ] No third locale introduced.

## API & security

- [ ] `AppError` is the single Problem Details parse path (`shared/api/errors.ts`).
- [ ] Auth via `entities/auth` `authStore` (localStorage) + `Authorization: Bearer` + `credentials: 'include'` (NeNe Records pattern).
- [ ] Fail closed: 401 → clear session + login, 403 → forbidden; no silent unauthenticated mutation.
- [ ] API JSON kept snake_case (no rename to camelCase in transit).
- [ ] RBAC UI gating matches API capability; treated as UX only.
- [ ] No `dangerouslySetInnerHTML` without DOMPurify + Issue.

## Storybook

- [ ] Every new `shared/ui` primitive / composed component has a colocated `*.stories.tsx`.
- [ ] Story documents In / Out / Does-not contract.
- [ ] No stories under `features/`, `pages/`, `entities/`.

## Testing

- [ ] New entity: mapper test (+ query-key test if non-trivial).
- [ ] New `use-{feature}` hook: colocated hook test against MSW (primary query + each mutation).
- [ ] Feature UI: happy path + primary Problem Details error path.
- [ ] Queries use role/label/accessible name; `userEvent.setup()`; `createTestQueryClient()`.
- [ ] MSW handler shapes match OpenAPI.

## A11y

- [ ] `eslint-plugin-jsx-a11y` clean; form errors via `aria-describedby`; focus managed on route/modal change.

---

## Verification

```bash
npm run check --prefix frontend
```

(type-check + lint + format + test + knip + build-storybook)
