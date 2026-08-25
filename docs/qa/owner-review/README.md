# Owner-review material — the shape (#439)

**What this is.** The material the owner looks at before a kit-migration wave goes to
production. Ruling 2026-08-23: the design-preservation constraint is lifted; in exchange
**every ship's migration passes the owner's visual check before it is deployed — once per
wave, not per PR.** The acceptance criterion, in the owner's words: *"アプリケーションが正常に
動くこと、人間が見てインターフェースが整っていること"* — it works, and it looks put together.
Not "it is identical", not "it conforms".

**What it is not.** Not a comparison and not a gate that decides anything.
`batch6-kit-parity` measures computed styles and reports differences; this batch only shows
pictures. A person decides.

## Run

```bash
# 1. local target up: API 8600 with DEMO_MODE=1, frontend 5186
DEMO_MODE=1 docker compose up -d app
# after a kit bump: refresh the container's node_modules and drop Vite's prebundle
docker compose exec frontend npm install --no-package-lock
docker compose exec frontend rm -rf node_modules/.vite/deps && docker compose restart frontend

# 2. capture
npm run e2e:live --prefix frontend -- batch8
#   NENE_VAULT_PARITY_LOCAL_URL=http://localhost:4173   # another local build / preview
#   NENE_VAULT_OWNER_REVIEW_DIR=w1                       # fixed directory name (default: today)
```

Output: `docs/qa/owner-review/<date>/index.html` + PNGs + `meta.json`. ~45 s, 28 captures
(7 screens × 2 viewports × 2 sides). **Gitignored** — the material is per-wave and
disposable; the verdict goes on the issue, and this generator is what persists.

Both sides are seated through `/demo/standard` (admin, disposable org, no credentials). One
org is minted per side per run; the viewport is switched on the same page.

## Read

| column | is |
| --- | --- |
| **production** | whatever `vault.ayane.co.jp` served at run time — **not "the current design"**. Establish which build that is before reading a row (from 2026-08-26 01:32:50 JST: `c6890e4`, 0.9.2.1; earlier that night: `d2f0920`, 0.9.2; 2026-08-25: `6da5eb0`, 0.9.1; before that: `97da1e0`, 2026-07-12) |
| **local** | the build named in `meta.json` (`local HEAD`, `local nene2-ui`) |

Content differs between the columns by design (each side has its own disposable org):
look at the chrome — buttons, inputs, tables, the modal, the rail — not at the rows.

Screens: `home` · `documents` · `upload-modal` (viewport-only; the kit `Modal` is a native
`<dialog>`) · `document-detail` · `audit` · `export` · `settings`, each at desktop 1280×800
and mobile 375×812.

A cell reading **not captured** carries the reason. It is reported, never dropped: a screen
that could not be reached is a finding, not a blank.

## Record

The owner's verdict is **GO / NG per screen, on the wave's tracking issue** (for W1: #439),
with the `meta.json` values (`local HEAD`, kit version, production build) quoted so the
verdict is tied to what was looked at. Expect a NG round: W1b's first bundle came back GO 4 /
NG 3 because the kit's default look replaced this product's; the fix was slot values and
`className`, not a revert — ship those *with* the migration next time (see `tableChrome.ts`,
`badgeBase.ts`, `paginationChrome.ts`). An NG becomes an issue on this repo or on the kit —
not a special case on the screen (DoD step 3).

## Guards

- **Unstyled build** (the `@source` regression, #387): checked on `/documents` on the local
  side before anything is captured. The landing page is this product's own markup and carries
  no kit utility even when fully styled, so the guard is not there.
- **Nothing captured on either side** fails the run. Partial capture is reported in the table.

## For another ship

Copy the spec, replace `SCREENS` (rail labels, URL patterns, the one modal worth showing),
keep the two viewports and the two-column `index.html`. The three local-side prerequisites
(`DEMO_MODE`, container `node_modules`, `.vite/deps`) are this repo's; substitute your own.
Establish what your production build *is* before reading the left column.
