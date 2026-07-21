import type { ReactNode } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { roleHasCapability, type Capability } from '@/shared/auth/capabilities';
import { BrandMark } from '@/shared/ui/primitives/BrandMark';
import { LanguageSwitcher, type LocaleCode } from './LanguageSwitcher';

/** Stable identifier for a rail nav item — the key into the resolved-label maps. */
type NavId = 'home' | 'documents' | 'audit' | 'settings' | 'users' | 'export';
/** Stable identifier for a rail group heading. */
type GroupId = 'documents' | 'admin';

interface NavItem {
  id: NavId;
  to: string;
  icon: ReactNode;
  /** Group heading rendered above this item (new section), by stable id. */
  groupId?: GroupId;
  /**
   * Capability the current role must hold for this route (mirrors the backend
   * CapabilityResolver). Omitted for routes open to every authenticated role
   * (e.g. Home). Items the role cannot use are hidden so they never dead-end a
   * viewer on the Forbidden page (#174).
   */
  requiredCapability?: Capability;
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
  /** Raw role key (e.g. 'admin'); drives capability gating. */
  userRole?: string | undefined;
  /** Content column width: full (default), 'mid', or 'narrow' (forms). */
  width?: 'default' | 'mid' | 'narrow' | undefined;
  /** Resolved nav item labels, keyed by route id (consumer holds the i18n). */
  navLabels: Record<NavId, string>;
  /** Resolved group headings, keyed by group id. */
  groupLabels: Record<GroupId, string>;
  /** Resolved aria-label for the rail nav. */
  menuLabel: string;
  /** Resolved aria-label for the log-out control. */
  logoutLabel: string;
  /** Resolved aria-label for the breadcrumb nav. */
  breadcrumbLabel: string;
  /** Resolved role label for the footer, or null/undefined when there is none. */
  roleLabel?: ReactNode;
  /** Resolved label for the language switcher. */
  languageLabel: string;
  /** Currently selected locale (forwarded to the language switcher). */
  locale: LocaleCode;
  /** Called with the chosen locale when the language switcher changes. */
  onLocaleChange: (locale: LocaleCode) => void;
  /** Selectable locales, in display order (forwarded to the language switcher). */
  locales: readonly LocaleCode[];
}

const WIDTH_CLASS: Record<NonNullable<AppShellProps['width']>, string> = {
  default: 'content space-y-5.5 max-md:space-y-4.5',
  mid: 'content is-mid space-y-5.5 max-md:space-y-4.5',
  narrow: 'content is-narrow space-y-5.5 max-md:space-y-4.5',
};

export function AppShell({
  children,
  onLogout,
  userEmail,
  userRole,
  width = 'default',
  navLabels,
  groupLabels,
  menuLabel,
  logoutLabel,
  breadcrumbLabel,
  roleLabel,
  languageLabel,
  locale,
  onLocaleChange,
  locales,
}: AppShellProps) {
  const navigate = useNavigate();
  const { pathname } = useLocation();

  const nav: NavItem[] = [
    { id: 'home', to: '/', icon: HomeIcon },
    {
      id: 'documents',
      to: '/documents',
      icon: DocIcon,
      groupId: 'documents',
      requiredCapability: 'ViewDocuments',
    },
    {
      id: 'audit',
      to: '/audit',
      icon: AuditIcon,
      groupId: 'admin',
      requiredCapability: 'ManageVaultSettings',
    },
    {
      id: 'settings',
      to: '/settings',
      icon: SettingsIcon,
      requiredCapability: 'ManageVaultSettings',
    },
    {
      id: 'users',
      to: '/users',
      icon: UsersIcon,
      requiredCapability: 'ManageUsers',
    },
    {
      id: 'export',
      to: '/export',
      icon: ExportIcon,
      requiredCapability: 'ExportDocuments',
    },
  ];

  const visibleNav = nav.filter(
    (item) =>
      item.requiredCapability === undefined || roleHasCapability(userRole, item.requiredCapability),
  );

  const isActive = (to: string): boolean =>
    to === '/' ? pathname === '/' : pathname.startsWith(to);

  const activeItem = [...nav].reverse().find((n) => isActive(n.to));
  const leafLabel = activeItem !== undefined ? navLabels[activeItem.id] : '';

  const go = (to: string): void => {
    void navigate(to);
  };

  const avatarLetter = userEmail !== undefined && userEmail !== '' ? userEmail.charAt(0) : '?';

  return (
    <div className="layout">
      <aside className="rail">
        <div className="rail-brand">
          <BrandMark size={34} className="brand-mark text-x-seal-bright" title="NeNe Vault" />
          <div>
            <div className="brand-name">NeNe Vault</div>
            <div className="brand-sub">Document Archive</div>
          </div>
        </div>

        <nav className="rail-nav" aria-label={menuLabel}>
          {visibleNav.map((item) => (
            <div key={item.to} className="contents">
              {item.groupId !== undefined && (
                <div className="rail-group">{groupLabels[item.groupId]}</div>
              )}
              <button
                type="button"
                className={isActive(item.to) ? 'rail-link is-active' : 'rail-link'}
                aria-current={isActive(item.to) ? 'page' : undefined}
                onClick={() => {
                  go(item.to);
                }}
              >
                {item.icon}
                {navLabels[item.id]}
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
            {roleLabel !== null && roleLabel !== undefined && <span>{roleLabel}</span>}
          </div>
          <button type="button" className="out" onClick={onLogout} aria-label={logoutLabel}>
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

      <div className="flex flex-col min-w-0">
        <header className="topbar">
          <nav className="crumbs" aria-label={breadcrumbLabel}>
            {pathname === '/' || leafLabel === '' ? (
              <b>{navLabels.home}</b>
            ) : (
              <>
                <span>{navLabels.home}</span>
                <span className="sep">/</span>
                <b>{leafLabel}</b>
              </>
            )}
          </nav>
          <div className="right">
            <LanguageSwitcher
              label={languageLabel}
              locale={locale}
              onLocaleChange={onLocaleChange}
              locales={locales}
            />
          </div>
        </header>

        <main className={WIDTH_CLASS[width]}>{children}</main>
      </div>
    </div>
  );
}
