/**
 * The same MSW handlers the unit tests use, wired for a real browser.
 *
 * Why this exists: a kit upgrade can only be checked by looking at it — measured 2026-08-28,
 * none of this product's eight checks can see a wrong slot name, and neither can 241 unit
 * tests. Looking at it normally means bringing the stack up, and when Docker is unreachable
 * (WSL integration off, 2026-08-26 and again 2026-08-28) there is otherwise nothing to look
 * at. With the API answered in-browser by the handlers in `handlers.ts`, the real screens
 * render — real router, real cascade, real layer order — with no backend at all.
 *
 * 🔴 This is a **development** path. It is reached only when Vite is in dev mode AND
 * `VITE_MSW=1` is set, so a production build cannot start it: `import.meta.env.DEV` is a
 * compile-time constant there and the whole branch, including this module, is dropped.
 * `tools/assert-no-msw-in-build.mjs` checks that claim against the built bundle rather than
 * trusting it.
 */
import { setupWorker } from 'msw/browser';
import { handlers } from './handlers';

export const worker = setupWorker(...handlers);
