import type { ReactNode } from 'react';
import { useNavigate } from 'react-router-dom';
import { authStore } from '@/entities/auth';
import { useTranslation } from '@/shared/i18n/use-translation';
import type { MessageKey } from '@/shared/i18n/catalogs';
import { roleHasCapability, type Capability } from '@/shared/auth/capabilities';
import { AppShell } from '@/shared/ui';

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
    <AppShell onLogout={handleLogout} userEmail={session?.email} userRole={session?.role}>
      <div className="titlebar">
        <span className="eyebrow">{t('home.eyebrow')}</span>
        <h1 className="page-title">{t('home.title')}</h1>
        <p className="lede">{t('home.lede')}</p>
      </div>

      <div>
        <div className="row gap-sm mb-stack-sm">
          <span className="tick" />
          <h2 className="subtitle">{t('home.quick_access')}</h2>
        </div>
        <div className="grid-2">
          {visibleLinks.map((link) => (
            <button
              key={link.to}
              type="button"
              className="qlink"
              onClick={() => {
                void navigate(link.to);
              }}
            >
              <span className="ic">{link.icon}</span>
              <span className="flex1">
                <b>{t(link.titleKey)}</b>
                <span>{t(link.subKey)}</span>
              </span>
              <span className="arr">→</span>
            </button>
          ))}
        </div>
      </div>
    </AppShell>
  );
}
