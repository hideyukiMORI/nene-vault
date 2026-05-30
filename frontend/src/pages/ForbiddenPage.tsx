import { useTranslation } from '@/shared/i18n/use-translation';
import { Text } from '@/shared/ui';

export function ForbiddenPage() {
  const { t } = useTranslation();
  return (
    <div className="flex min-h-screen items-center justify-center bg-surface">
      <Text tone="danger">{t('problem.forbidden')}</Text>
    </div>
  );
}
