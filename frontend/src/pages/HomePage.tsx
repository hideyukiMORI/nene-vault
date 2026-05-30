import { useNavigate } from 'react-router-dom';
import { authStore } from '@/entities/auth';
import { useTranslation } from '@/shared/i18n/use-translation';
import { Button, Stack, Text } from '@/shared/ui';
import { LanguageSwitcher } from '@/shared/ui/components/LanguageSwitcher';

export function HomePage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const session = authStore.getSession();

  function handleLogout() {
    authStore.clearSession();
    navigate('/login', { replace: true });
  }

  return (
    <div className="min-h-screen bg-surface">
      <header className="flex items-center gap-inline-lg border-b border-border bg-surface-raised px-inline-lg py-stack-sm">
        <Text as="span" className="text-heading-sm">
          NeNe Vault
        </Text>
        <nav className="flex gap-inline-md">
          <Text as="span" tone="muted">
            {t('navigation.documents')}
          </Text>
          <Text as="span" tone="muted">
            {t('navigation.audit_events')}
          </Text>
          <Text as="span" tone="muted">
            {t('navigation.settings')}
          </Text>
        </nav>
        <div className="ml-auto flex items-center gap-inline-md">
          <LanguageSwitcher />
          {session !== null && (
            <Text as="span" tone="muted">
              {session.email} ({t(`user.role.${session.role}`)})
            </Text>
          )}
          <Button variant="secondary" onClick={handleLogout}>
            {t('navigation.logout')}
          </Button>
        </div>
      </header>
      <main className="p-inline-lg">
        <Stack gap="md">
          <Text as="h1" className="text-heading-md">
            {t('navigation.documents')}
          </Text>
          <Text tone="muted">{t('document.list.empty')}</Text>
        </Stack>
      </main>
    </div>
  );
}
