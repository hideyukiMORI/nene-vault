import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { RouterProvider } from 'react-router-dom';
import { Providers } from '@/app/providers';
import { RootErrorBoundary } from '@/app/root-error-boundary';
import { router } from '@/app/router';

const rootElement = document.getElementById('root');
if (rootElement === null) {
  throw new Error('Root element #root not found.');
}

/**
 * 🔴 Development only, and only when asked for: `VITE_MSW=1 npm run dev`.
 *
 * `import.meta.env.DEV` is a compile-time constant in a production build, so this branch and
 * the dynamic import inside it are dropped there — msw is a devDependency and must never
 * reach a bundle that ships. `tools/assert-no-msw-in-build.mjs` verifies that against the
 * built output instead of taking it on trust.
 *
 * What it is for: rendering the real screens in a real browser with no backend, which is the
 * only way to check a kit upgrade when the local stack cannot start. See `tests/msw/browser.ts`.
 */
if (import.meta.env.DEV && import.meta.env.VITE_MSW === '1') {
  const { worker } = await import('@tests/msw/browser');
  await worker.start({ onUnhandledRequest: 'bypass' });
}

createRoot(rootElement).render(
  <StrictMode>
    <RootErrorBoundary>
      <Providers>
        <RouterProvider router={router} />
      </Providers>
    </RootErrorBoundary>
  </StrictMode>,
);
