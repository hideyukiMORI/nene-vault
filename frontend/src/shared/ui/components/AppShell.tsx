import { dynamicMessageKey, type MessageKey } from '@/shared/i18n/catalogs';
import type { ReactNode } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useTranslation } from '@/shared/i18n/use-translation';
import { BrandMark } from '@/shared/ui/primitives/BrandMark';
import { LanguageSwitcher } from './LanguageSwitcher';

interface NavItem {
  to: string;
  labelKey: MessageKey;
  icon: ReactNode;
  /** Locale key for a group heading rendered above this item (new section). */
  groupKey?: MessageKey;
}

const HomeIcon = (
  <svg
    viewBox="0 0 24 24"
    fill="none"
    strokeWidth="1.7"
    strokeLinecap="round"
    strokeLinejoin="round"
  >
    <path d="M3 10.5 12 3l9 7.5" />
    <path d="M5 9.5V20h14V9.5" />
  </svg>
);
const DocIcon = (
  <svg
    viewBox="0 0 24 24"
    fill="none"
    strokeWidth="1.7"
    strokeLinecap="round"
    strokeLinejoin="round"
  >
    <path d="M6 2.5h7l5 5V21a.5.5 0 0 1-.5.5h-11A.5.5 0 0 1 6 21Z" />
    <path d="M13 2.5V8h5" />
    <path d="M9 13h6M9 16.5h6" />
  </svg>
);
const AuditIcon = (
  <svg
    viewBox="0 0 24 24"
    fill="none"
    strokeWidth="1.7"
    strokeLinecap="round"
    strokeLinejoin="round"
  >
    <path d="M4 5h16M4 12h16M4 19h10" />
    <circle cx="18" cy="19" r="2.4" />
  </svg>
);
const SettingsIcon = (
  <svg
    viewBox="0 0 24 24"
    fill="none"
    strokeWidth="1.7"
    strokeLinecap="round"
    strokeLinejoin="round"
  >
    <path d="M5 7h14M5 12h14M5 17h14" />
  </svg>
);
const UsersIcon = (
  <svg
    viewBox="0 0 24 24"
    fill="none"
    strokeWidth="1.7"
    strokeLinecap="round"
    strokeLinejoin="round"
  >
    <circle cx="9" cy="8" r="3.2" />
    <path d="M3.5 20c0-3.3 2.5-5.5 5.5-5.5s5.5 2.2 5.5 5.5" />
    <path d="M16 5.2a3.2 3.2 0 0 1 0 6M18 14.8c2.2.5 3.8 2.4 3.8 5" />
  </svg>
);
const ExportIcon = (
  <svg
    viewBox="0 0 24 24"
    fill="none"
    strokeWidth="1.7"
    strokeLinecap="round"
    strokeLinejoin="round"
  >
    <path d="M12 3v11" />
    <path d="m8 10 4 4 4-4" />
    <path d="M4 17v2.5a.5.5 0 0 0 .5.5h15a.5.5 0 0 0 .5-.5V17" />
  </svg>
);

interface AppShellProps {
  children: ReactNode;
  onLogout: () => void;
  /** Signed-in user's email (rail footer). */
  userEmail?: string | undefined;
  /** Raw role key (e.g. 'admin'); translated via `user.role.*`. */
  userRole?: string | undefined;
  /** Content column width: full (default), 'mid', or 'narrow' (forms). */
  width?: 'default' | 'mid' | 'narrow';
}

const WIDTH_CLASS: Record<NonNullable<AppShellProps['width']>, string> = {
  default: 'content stack-lg',
  mid: 'content is-mid stack-lg',
  narrow: 'content is-narrow stack-lg',
};

export function AppShell({
  children,
  onLogout,
  userEmail,
  userRole,
  width = 'default',
}: AppShellProps) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { pathname } = useLocation();

  const nav: NavItem[] = [
    { to: '/', labelKey: 'navigation.home', icon: HomeIcon },
    {
      to: '/documents',
      labelKey: 'document.list.title',
      icon: DocIcon,
      groupKey: 'navigation.documents',
    },
    {
      to: '/audit',
      labelKey: 'navigation.audit_events',
      icon: AuditIcon,
      groupKey: 'navigation.group_admin',
    },
    { to: '/settings', labelKey: 'navigation.settings', icon: SettingsIcon },
    { to: '/users', labelKey: 'navigation.users', icon: UsersIcon },
    { to: '/export', labelKey: 'navigation.export', icon: ExportIcon },
  ];

  const isActive = (to: string): boolean =>
    to === '/' ? pathname === '/' : pathname.startsWith(to);

  const activeItem = [...nav].reverse().find((n) => isActive(n.to));
  const leafLabel = activeItem !== undefined ? t(activeItem.labelKey) : '';

  const go = (to: string): void => {
    void navigate(to);
  };

  const avatarLetter = userEmail !== undefined && userEmail !== '' ? userEmail.charAt(0) : '?';
  const roleLabel =
    userRole !== undefined && userRole !== ''
      ? t(dynamicMessageKey(`user.role.${userRole}`))
      : null;

  return (
    <div className="layout">
      <aside className="rail">
        <div className="rail-brand">
          <BrandMark size={34} className="brand-mark text-seal-bright" title="NeNe Vault" />
          <div>
            <div className="brand-name">NeNe Vault</div>
            <div className="brand-sub">Document Archive</div>
          </div>
        </div>

        <nav className="rail-nav" aria-label={t('navigation.menu')}>
          {nav.map((item) => (
            <div key={item.to} className="contents">
              {item.groupKey !== undefined && <div className="rail-group">{t(item.groupKey)}</div>}
              <button
                type="button"
                className={isActive(item.to) ? 'rail-link is-active' : 'rail-link'}
                aria-current={isActive(item.to) ? 'page' : undefined}
                onClick={() => {
                  go(item.to);
                }}
              >
                {item.icon}
                {t(item.labelKey)}
              </button>
            </div>
          ))}
        </nav>

        <div className="rail-foot">
          <div className="avatar" aria-hidden="true">
            {avatarLetter}
          </div>
          <div className="who">
            <b>{userEmail ?? '—'}</b>
            {roleLabel !== null && <span>{roleLabel}</span>}
          </div>
          <button
            type="button"
            className="out"
            onClick={onLogout}
            aria-label={t('navigation.logout')}
          >
            <svg
              viewBox="0 0 24 24"
              fill="none"
              strokeWidth="1.7"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <path d="M14 4h4.5a.5.5 0 0 1 .5.5v15a.5.5 0 0 1-.5.5H14" />
              <path d="M9 12h9M14 8l4 4-4 4" />
            </svg>
          </button>
        </div>
      </aside>

      <div className="main">
        <header className="topbar">
          <nav className="crumbs" aria-label={t('navigation.breadcrumb')}>
            {pathname === '/' || leafLabel === '' ? (
              <b>{t('navigation.home')}</b>
            ) : (
              <>
                <span>{t('navigation.home')}</span>
                <span className="sep">/</span>
                <b>{leafLabel}</b>
              </>
            )}
          </nav>
          <div className="right">
            <LanguageSwitcher />
          </div>
        </header>

        <main className={WIDTH_CLASS[width]}>{children}</main>
      </div>
    </div>
  );
}
