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

createRoot(rootElement).render(
  <StrictMode>
    <RootErrorBoundary>
      <Providers>
        <RouterProvider router={router} future={{ v7_startTransition: true }} />
      </Providers>
    </RootErrorBoundary>
  </StrictMode>,
);
