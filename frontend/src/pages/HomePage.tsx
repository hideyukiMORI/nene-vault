import { Grid, Stack } from '@hideyukimori/nene2-ui';
import type { ReactNode } from 'react';
import { useNavigate } from 'react-router-dom';
import { authStore } from '@/shared/api/auth-session';
import { useTranslation } from '@/shared/i18n/use-translation';
import type { MessageKey } from '@/shared/i18n/catalogs';
import { roleHasCapability, type Capability } from '@/shared/auth/capabilities';
import { AppChrome } from '@/features/app-chrome';

interface QuickLink {
  to: string;
  titleKey: MessageKey;
  subKey: MessageKey;
  icon: ReactNode;
  /**
   * Capability the current role must hold to reach this route (mirrors the rail
   * gating in AppShell and the backend CapabilityResolver). Cards the role
   * cannot use are hidden so a viewer's home never offers admin-only actions
   * (#182, follow-up to #174).
   */
  requiredCapability: Capability;
}

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

const LINKS: QuickLink[] = [
  {
    to: '/documents',
    titleKey: 'document.list.title',
    subKey: 'home.link_documents',
    icon: DocIcon,
    requiredCapability: 'ViewDocuments',
  },
  {
    to: '/audit',
    titleKey: 'navigation.audit_events',
    subKey: 'home.link_audit',
    icon: AuditIcon,
    requiredCapability: 'ManageVaultSettings',
  },
  {
    to: '/settings',
    titleKey: 'navigation.settings',
    subKey: 'home.link_settings',
    icon: SettingsIcon,
    requiredCapability: 'ManageVaultSettings',
  },
  {
    to: '/export',
    titleKey: 'navigation.export',
    subKey: 'home.link_export',
    icon: ExportIcon,
    requiredCapability: 'ExportDocuments',
  },
];

/** Links the quick-access group to its heading — see the note at the `<Grid>`. */
const QUICK_ACCESS_HEADING_ID = 'home-quick-access-heading';

export function HomePage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const session = authStore.getSession();

  const visibleLinks = LINKS.filter((link) =>
    roleHasCapability(session?.role, link.requiredCapability),
  );

  function handleLogout() {
    authStore.clearSession();
    void navigate('/login', { replace: true });
  }

  return (
    <AppChrome onLogout={handleLogout} userEmail={session?.email} userRole={session?.role}>
      <Stack gap="2xs">
        <span className="text-2xs tracking-eyebrow uppercase text-x-brass-deep font-semibold">
          {t('home.eyebrow')}
        </span>
        <h1 className="text-h1 font-semibold tracking-title text-x-ink-deep">{t('home.title')}</h1>
        <p className="text-text-muted text-sm max-w-lede">{t('home.lede')}</p>
      </Stack>

      <div>
        <div className="flex items-center gap-2 mb-stack-sm">
          <span className="inline-block w-0.75 h-3.75 bg-x-brass rounded-px flex-none" />
          <h2
            id={QUICK_ACCESS_HEADING_ID}
            className="text-h2 font-semibold tracking-tight text-x-ink-deep flex items-center gap-2.25"
          >
            {t('home.quick_access')}
          </h2>
        </div>
        {/* 🔴 A labelled region, not decoration. The cards were only distinguishable from the
            rail's buttons by a styling class — which is what the drain took away (#426), and
            which was never the right handle: a class that exists to paint something was
            load-bearing for "which buttons are the quick-access cards". Naming the group says
            it in the accessibility tree instead, where it is also true for a screen reader. */}
        <Grid
          cols={{ base: 1, sm: 2 }}
          gap="sm"
          role="group"
          aria-labelledby={QUICK_ACCESS_HEADING_ID}
        >
          {visibleLinks.map((link) => (
            <button
              key={link.to}
              type="button"
              /* Regenerated from `.qlink` and its six descendant rules (#426).
                 🔴 The retired `.qlink span` applied `text-xs` + `text-muted` to EVERY span
                 inside the card — the icon box, the wrapper, the subtitle and the arrow —
                 with the more specific `.ic` / `.arr` rules winning only on colour. The
                 utilities below reproduce that per element rather than re-creating a
                 descendant selector.
                 🔴 `leading-inherit` on each: all four rules set a font-size and no
                 line-height, which is the shape 判例40 covers. */
              className="flex items-center gap-3.5 px-4.5 py-4 bg-surface-raised border border-border rounded-md no-underline text-inherit shadow-sm qlink-transition cursor-pointer text-left w-full hover:border-border-strong hover:bg-surface-overlay"
              onClick={() => {
                void navigate(link.to);
              }}
            >
              <span className="w-9.5 h-9.5 flex-none rounded-sm bg-accent-soft text-accent flex items-center justify-center text-xs leading-inherit [&_svg]:w-4.75 [&_svg]:h-4.75 [&_svg]:stroke-current">
                {link.icon}
              </span>
              <span className="flex-1 min-w-0 text-xs leading-inherit text-text-muted">
                <b className="block text-x-ink-deep text-body leading-inherit font-semibold">
                  {t(link.titleKey)}
                </b>
                <span className="text-xs leading-inherit text-text-muted">{t(link.subKey)}</span>
              </span>
              <span className="ml-auto text-xs leading-inherit text-text-faint">→</span>
            </button>
          ))}
        </Grid>
      </div>
    </AppChrome>
  );
}
