import { Navigate } from 'react-router-dom';
import { authStore } from '@/entities/auth';
import type { ReactNode } from 'react';

/** Fail closed: render children only when a session exists, else redirect to /login. */
export function AuthGate({ children }: { children: ReactNode }) {
  if (authStore.getToken() === null) {
    return <Navigate to="/login" replace />;
  }
  return <>{children}</>;
}
