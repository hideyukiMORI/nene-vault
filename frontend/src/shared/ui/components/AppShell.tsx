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

/* Regenerated from `.content` / `.content.is-mid` / `.content.is-narrow` (#428).
   🔴 The three width caps differ only in `max-width`, so the shared part is one string and
   the cap is appended — the retired rules composed the same way (`.content` plus a modifier).
   Breakpoints are the retired rules' exactly: ≥1500px widens to 1280px, 768–1023px narrows
   the side padding, below 768px all three collapse to full width. */
const CONTENT_BASE =
  'w-full mx-auto pt-7.5 px-7 pb-14 wide:max-w-content-wide ' +
  'md:max-lg:px-5.5 max-md:px-4 max-md:py-5 max-md:max-w-full ' +
  'space-y-5.5 max-md:space-y-4.5';

/* 🔴 Only the default width. The retired rules were
     `.content, .content.is-narrow, .content.is-mid { padding: 20px 16px }`   (0,2,0)
     `.content { padding-bottom: 96px }`                                      (0,1,0)
   so on a narrow or mid page the *shorthand* won on specificity and the bottom padding
   stayed at 20px — the 96px reached the default width only. Measured 2026-08-24: putting
   it on all three moved mobile Settings from 664.59px tall to 740.59px. */
const CONTENT_BOTTOM_BAR = 'max-md:pb-24';

const WIDTH_CLASS: Record<NonNullable<AppShellProps['width']>, string> = {
  default: `${CONTENT_BASE} ${CONTENT_BOTTOM_BAR} max-w-content`,
  mid: `${CONTENT_BASE} max-w-content-mid`,
  narrow: `${CONTENT_BASE} max-w-content-narrow`,
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
    <div
      /* Regenerated from `.layout` (#428). The two grid templates are `@utility` because they
         reference `--rail-width`, a named token rather than a step of a scale. The breakpoints
         are the retired rule's exactly: 768–1023px compact, below 768px stacked. */
      className="grid grid-cols-shell min-h-screen md:max-lg:grid-cols-shell-compact max-md:block"
    >
      <aside className="rail">
        <div className="rail-brand">
          <BrandMark
            size={34}
            className="w-8.5 h-8.5 flex-none block text-x-seal-bright"
            title="NeNe Vault"
          />
          <div>
            <div className="font-serif text-brand font-semibold text-x-rail-ink leading-brand tracking-wordmark whitespace-nowrap">
              NeNe Vault
            </div>
            <div className="text-3xs tracking-brand text-x-brass uppercase mt-0.5 font-medium">
              Document Archive
            </div>
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
                className="rail-link"
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
        {/* Regenerated from the retired `.topbar` / `.topbar .right` component classes (#419).
            The three values that are not steps of any scale became `@utility` (single
            declaration each, per the gate in index.css): `--z-topbar`, `--topbar-height`,
            and `saturate(1.2)`. The rest are utilities on the scale.
            🔴 `bg-surface-raised/92` is the 92% alpha the old rule wrote as a literal
            `oklch(99.4% 0.004 83 / 92%)` — the same colour as `--color-surface-raised`. */}
        <header className="sticky top-0 z-topbar flex items-center gap-4 h-topbar px-7 bg-surface-raised/92 backdrop-saturate-topbar border-b border-border max-md:h-13 max-md:pr-15 max-md:pl-4 max-md:gap-2.5">
          {/* Regenerated from `.crumbs` / `.crumbs b` (#419 wave 2).
              🔴 `leading-inherit` is load-bearing, not decoration. The retired rule set a
              font-size and nothing else, so the line-height was inherited — but the theme
              overrides only the *value* of `--text-sm` / `--text-xs`, leaving Tailwind's
              companion `--text-*--line-height` alive, so a bare `text-sm` drags one in
              (判例40). Without this the bar's text box changes height silently.
              The `b` rule is written on the two `<b>` elements themselves rather than as a
              descendant variant: there are only two, and a descendant selector is what this
              drain exists to remove. */}
          <nav
            className="flex items-center gap-2 text-sm leading-inherit text-text-muted min-w-0 max-md:text-xs max-md:leading-inherit max-md:overflow-hidden max-md:whitespace-nowrap max-md:text-ellipsis"
            aria-label={breadcrumbLabel}
          >
            {pathname === '/' || leafLabel === '' ? (
              <b className="text-x-ink-deep font-semibold whitespace-nowrap overflow-hidden text-ellipsis">
                {navLabels.home}
              </b>
            ) : (
              <>
                <span>{navLabels.home}</span>
                <span className="text-text-faint">/</span>
                <b className="text-x-ink-deep font-semibold whitespace-nowrap overflow-hidden text-ellipsis">
                  {leafLabel}
                </b>
              </>
            )}
          </nav>
          {/* `.topbar .right` — `ml-auto` is what pushes it to the end of the bar. */}
          <div className="ml-auto flex items-center gap-3.5 max-md:gap-2">
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
