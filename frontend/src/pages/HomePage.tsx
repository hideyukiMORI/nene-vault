import { clearToken } from '../api/client';
import { useLocale } from '../locale/useLocale';
import { LanguageSwitcher } from '../components/LanguageSwitcher';

interface HomePageProps {
  email: string;
  role: string;
  onLogout: () => void;
}

export function HomePage({ email, role, onLogout }: HomePageProps) {
  const { t } = useLocale();

  function handleLogout() {
    clearToken();
    onLogout();
  }

  return (
    <div className="home-page">
      <header className="app-header">
        <strong>NeNe Vault</strong>
        <nav>
          <span>{t('navigation.documents')}</span>
          <span>{t('navigation.audit_events')}</span>
          <span>{t('navigation.settings')}</span>
        </nav>
        <div className="app-header-right">
          <LanguageSwitcher />
          <span className="user-badge">
            {email} ({t(`user.role.${role}`)})
          </span>
          <button type="button" onClick={handleLogout}>
            {t('navigation.logout')}
          </button>
        </div>
      </header>
      <main className="home-main">
        <h1>{t('navigation.documents')}</h1>
        <p>{t('document.list.empty')}</p>
      </main>
    </div>
  );
}
