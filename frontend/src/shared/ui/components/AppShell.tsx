import { useNavigate, useLocation } from 'react-router-dom';
import { useTranslation } from '@/shared/i18n/use-translation';
import { LanguageSwitcher } from './LanguageSwitcher';

interface NavLinkProps {
  label: string;
  active: boolean;
  onClick: () => void;
}

function NavLink({ label, active, onClick }: NavLinkProps) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={
        active
          ? 'text-brand text-body-sm font-medium'
          : 'text-muted text-body-sm hover:text-foreground transition-colors'
      }
    >
      {label}
    </button>
  );
}

interface AppShellProps {
  children: React.ReactNode;
  onLogout: () => void;
}

export function AppShell({ children, onLogout }: AppShellProps) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { pathname } = useLocation();

  const navLinks = [
    { to: '/documents', label: t('navigation.documents') },
    { to: '/audit', label: t('navigation.audit_events') },
    { to: '/settings', label: t('navigation.settings') },
    { to: '/users', label: t('navigation.users') },
    { to: '/export', label: t('navigation.export') },
  ];

  return (
    <div className="min-h-screen bg-surface">
      <header className="sticky top-0 z-40 flex items-center gap-inline-lg border-b border-border bg-surface-raised px-inline-lg py-stack-sm">
        <button
          type="button"
          onClick={() => {
            navigate('/');
          }}
          className="text-heading-sm font-semibold hover:opacity-80 transition-opacity"
        >
          NeNe Vault
        </button>

        <nav className="flex gap-inline-md">
          {navLinks.map((link) => (
            <NavLink
              key={link.to}
              label={link.label}
              active={pathname.startsWith(link.to)}
              onClick={() => {
                navigate(link.to);
              }}
            />
          ))}
        </nav>

        <div className="ml-auto flex items-center gap-inline-md">
          <LanguageSwitcher />
          <button
            type="button"
            onClick={onLogout}
            className="text-body-sm text-muted hover:text-foreground transition-colors"
          >
            {t('navigation.logout')}
          </button>
        </div>
      </header>

      <main className="mx-auto max-w-7xl px-inline-lg py-stack-lg">{children}</main>
    </div>
  );
}
