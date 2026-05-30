import { useState, type FormEvent } from 'react';
import { login } from '../api/auth';
import { ApiError } from '../api/client';
import { useLocale } from '../locale/useLocale';
import { LanguageSwitcher } from '../components/LanguageSwitcher';

interface LoginPageProps {
  onLoggedIn: (email: string, role: string) => void;
}

export function LoginPage({ onLoggedIn }: LoginPageProps) {
  const { t } = useLocale();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      const result = await login(email, password);
      onLoggedIn(result.email, result.role);
    } catch (err) {
      if (err instanceof ApiError && err.status === 401) {
        setError(t('auth.errors.invalid_credentials'));
      } else {
        setError(t('problem.internal_server_error'));
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="login-page">
      <header className="login-header">
        <LanguageSwitcher />
      </header>
      <form className="login-form" onSubmit={handleSubmit}>
        <h1>{t('auth.login.title')}</h1>

        <label>
          {t('auth.login.email_label')}
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder={t('auth.login.email_placeholder')}
            autoComplete="username"
            required
          />
        </label>

        <label>
          {t('auth.login.password_label')}
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder={t('auth.login.password_placeholder')}
            autoComplete="current-password"
            required
          />
        </label>

        {error !== null && <p className="login-error">{error}</p>}

        <button type="submit" disabled={submitting}>
          {submitting ? t('auth.login.logging_in') : t('auth.login.submit')}
        </button>
      </form>
    </div>
  );
}
