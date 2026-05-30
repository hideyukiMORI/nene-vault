import { useState } from 'react';
import { getToken } from './api/client';
import { LoginPage } from './pages/LoginPage';
import { HomePage } from './pages/HomePage';

interface Session {
  email: string;
  role: string;
}

export function App() {
  const [session, setSession] = useState<Session | null>(
    getToken() !== null ? { email: '', role: 'admin' } : null,
  );

  if (session === null) {
    return <LoginPage onLoggedIn={(email, role) => setSession({ email, role })} />;
  }

  return <HomePage email={session.email} role={session.role} onLogout={() => setSession(null)} />;
}
