import { useRestoreDocument } from '@/entities/document';
import { messageKeyForError } from '@/shared/i18n/map-problem-details';
import { useTranslation } from '@/shared/i18n/use-translation';
import { Button, Stack, Text } from '@/shared/ui';

interface RestoreModalProps {
  documentId: string;
  onClose: () => void;
}

export function RestoreModal({ documentId, onClose }: RestoreModalProps) {
  const { t } = useTranslation();
  const mutation = useRestoreDocument(onClose);
  const submitError =
    mutation.error !== null
      ? (messageKeyForError(mutation.error) ?? 'problem.internal_server_error')
      : null;

  return (
    <div
      role="dialog"
      aria-modal="true"
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    >
      <div className="w-full max-w-md rounded-xl border border-border bg-surface shadow-lg p-inline-lg">
        <Stack gap="md">
          <Text as="h2" className="text-heading-sm">
            {t('document.restore.title')}
          </Text>
          <Text tone="muted" className="text-body-sm">
            {t('document.restore.description')}
          </Text>
          {submitError !== null && (
            <Text tone="danger" className="text-body-sm">
              {t(submitError)}
            </Text>
          )}
          <div className="flex justify-end gap-inline-md">
            <Button
              type="button"
              variant="secondary"
              onClick={onClose}
              disabled={mutation.isPending}
            >
              {t('common.buttons.cancel')}
            </Button>
            <Button
              type="button"
              variant="primary"
              disabled={mutation.isPending}
              onClick={() => {
                mutation.mutate(documentId);
              }}
            >
              {mutation.isPending
                ? t('common.status.processing')
                : t('document.restore.confirm_button')}
            </Button>
          </div>
        </Stack>
      </div>
    </div>
  );
}
