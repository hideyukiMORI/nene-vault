# Frontend Standards

**Status: Phase 2 — scaffold implemented (PR #30).**

The admin UI is a React + TypeScript + Vite single-page app under `frontend/`.

## Toolchain

- **React 18 + TypeScript** (strict mode)
- **Vite 6** — dev server and production build
- **npm** — package manager; `package-lock.json` committed
- **ESLint** (flat config, typescript-eslint) + **Prettier**

## Commands

```bash
npm install --prefix frontend
npm run dev --prefix frontend       # dev server (proxies /admin, /health to :8080)
npm run build --prefix frontend     # tsc -b && vite build → frontend/dist
npm run check --prefix frontend     # type-check + lint + format (CI gate)
npm run format:fix --prefix frontend
```

`npm run check` is the frontend quality gate (mirrors `composer check` on the
backend). It must be green before merge.

## Locale integration (ADR 0005)

- UI strings come from the **repository-root `locales/ja.json` + `locales/en.json`**
  — the single source of truth (`docs/development/locale-guide.md`). They are
  imported via the `@locales` Vite alias; **never duplicated in components**.
- `LocaleProvider` (`src/locale/LocaleContext.tsx`) holds the active locale;
  `useLocale()` (`src/locale/useLocale.ts`) exposes `t(key, params)`.
- `t('auth.login.title')` looks up a dot-notation key; missing keys render the
  key itself (visible, not blank). `{{name}}` placeholders interpolate.
- `ja` is the default; `en` is selected via browser `Accept-Language` or the
  `LanguageSwitcher`. Choice persists in `localStorage`.
- **No third locale** — `ja` and `en` only.

## API client

- `src/api/client.ts` — typed `fetch` wrapper; attaches `Authorization: Bearer`
  from `localStorage`; throws `ApiError` carrying the RFC 9457 `ProblemDetails`.
- **API JSON stays snake_case** — the client passes fields through unchanged; it
  does not rename to camelCase.
- Dev server proxies `/admin/*` and `/health` to the backend at `:8080`.

## Structure

```
frontend/
  src/
    api/         # typed fetch client + endpoint modules (auth, …)
    locale/      # context, provider, useLocale hook, locale loading
    components/  # shared UI (LanguageSwitcher, …)
    pages/       # route-level screens (LoginPage, HomePage, …)
    App.tsx      # session gate: login vs authenticated shell
    main.tsx     # entry — wraps App in LocaleProvider
```

## Conventions

- Components: PascalCase file and export.
- Hooks: camelCase with `use` prefix; one hook per file when shared (react-refresh).
- Statutory labels (取引年月日, 取引金額, 取引先名) come from the locale files in
  Japanese in both locales (ADR 0005).
- Build output (`frontend/dist`) and `node_modules` are git-ignored.

## Not yet built (next Phase 2 slices)

- Document list / search / upload UI
- Metadata edit + void/restore + history views
- Audit log view, vault settings form, organization/user admin
- Manifest export UI
