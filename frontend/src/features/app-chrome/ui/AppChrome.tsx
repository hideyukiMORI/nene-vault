import type { ReactNode } from 'react';
import { dynamicMessageKey } from '@/shared/i18n/catalogs';
import { SUPPORTED_LOCALES, type SupportedLocale } from '@/shared/i18n/locales';
import { useTranslation } from '@/shared/i18n/use-translation';
import { AppShell } from '@/shared/ui/components/AppShell';

interface AppChromeProps {
  children: ReactNode;
  onLogout: () => void;
  /** Signed-in user's email (rail footer). */
  userEmail?: string | undefined;
  /** Raw role key (e.g. 'admin'); translated via `user.role.*` and drives gating. */
  userRole?: string | undefined;
  /** Content column width: full (default), 'mid', or 'narrow' (forms). */
  width?: 'default' | 'mid' | 'narrow';
}

/**
 * App-layer i18n adapter for the presentation-only `AppShell` (fleet 会議R1②):
 * resolves every user-facing string here and passes them down as props, so
 * `shared/ui` stays free of `@/shared/i18n`. All page chrome renders through
 * this wrapper instead of `AppShell` directly.
 */
export function AppChrome({ children, onLogout, userEmail, userRole, width }: AppChromeProps) {
  const { t, locale, setLocale } = useTranslation();

  const roleLabel =
    userRole !== undefined && userRole !== ''
      ? t(dynamicMessageKey(`user.role.${userRole}`))
      : null;

  return (
    <AppShell
      onLogout={onLogout}
      userEmail={userEmail}
      userRole={userRole}
      width={width}
      navLabels={{
        home: t('navigation.home'),
        documents: t('document.list.title'),
        audit: t('navigation.audit_events'),
        settings: t('navigation.settings'),
        users: t('navigation.users'),
        export: t('navigation.export'),
      }}
      groupLabels={{
        documents: t('navigation.documents'),
        admin: t('navigation.group_admin'),
      }}
      menuLabel={t('navigation.menu')}
      logoutLabel={t('navigation.logout')}
      breadcrumbLabel={t('navigation.breadcrumb')}
      roleLabel={roleLabel}
      languageLabel={t('navigation.language')}
      locale={locale}
      onLocaleChange={(next) => {
        setLocale(next as SupportedLocale);
      }}
      locales={SUPPORTED_LOCALES}
    >
      {children}
    </AppShell>
  );
}
