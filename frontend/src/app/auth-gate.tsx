import { useSyncExternalStore, type ReactNode } from 'react';
import { authStore } from '@/shared/api/auth-session';
import { LoginForm } from '@/features/login';

const subscribe = (listener: () => void) => authStore.subscribe(listener);
const getToken = () => authStore.getToken();

/**
 * Fail closed: render children only when a session exists; otherwise show the
 * login form IN PLACE (#168, clear/deal shape). The store is reactive, so a
 * 401-triggered clearSession() swaps the login form in at the current URL and
 * a successful login swaps the protected children back — no hard navigation,
 * no lost route.
 */
export function AuthGate({ children }: { children: ReactNode }) {
  const token = useSyncExternalStore(subscribe, getToken);

  if (token === null) {
    return (
      <LoginForm
        onLoggedIn={() => {
          // The store notified subscribers; this gate re-renders children.
        }}
      />
    );
  }

  return <>{children}</>;
}
