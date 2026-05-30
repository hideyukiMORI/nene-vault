import { useNavigate } from 'react-router-dom';
import { authStore } from '@/entities/auth';
import { useTranslation } from '@/shared/i18n/use-translation';
import { AppShell, Stack, Text } from '@/shared/ui';

export function HomePage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const session = authStore.getSession();

  function handleLogout() {
    authStore.clearSession();
    navigate('/login', { replace: true });
  }

  return (
    <AppShell onLogout={handleLogout}>
      <Stack gap="lg">
        <div>
          <Text as="h1" className="text-heading-md">
            NeNe Vault
          </Text>
          {session !== null && (
            <Text tone="muted" className="mt-stack-xs">
              {session.email}
            </Text>
          )}
        </div>

        <div className="grid grid-cols-2 gap-inline-lg">
          <button
            type="button"
            onClick={() => {
              navigate('/documents');
            }}
            className="rounded-lg border border-border bg-surface-raised p-stack-lg text-left hover:bg-surface transition-colors"
          >
            <Text as="h2" className="text-heading-sm">
              {t('navigation.documents')}
            </Text>
            <Text tone="muted" className="mt-stack-xs text-body-sm">
              {t('document.list.title')}
            </Text>
          </button>

          <button
            type="button"
            onClick={() => {
              navigate('/audit');
            }}
            className="rounded-lg border border-border bg-surface-raised p-stack-lg text-left hover:bg-surface transition-colors"
          >
            <Text as="h2" className="text-heading-sm">
              {t('navigation.audit_events')}
            </Text>
            <Text tone="muted" className="mt-stack-xs text-body-sm">
              {t('audit_event.list.title')}
            </Text>
          </button>
        </div>
      </Stack>
    </AppShell>
  );
}
