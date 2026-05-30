import { useTranslation } from '@/shared/i18n/use-translation';
import { Button, Input, Stack, Text } from '@/shared/ui';
import { useVoidDocumentForm } from '../hooks/use-void-document';

interface VoidModalProps {
  documentId: string;
  onClose: () => void;
}

export function VoidModal({ documentId, onClose }: VoidModalProps) {
  const { t } = useTranslation();
  const { form, onSubmit, isSubmitting, submitError } = useVoidDocumentForm(documentId, onClose);
  const {
    register,
    formState: { errors },
  } = form;

  return (
    <div
      role="dialog"
      aria-modal="true"
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    >
      <div className="w-full max-w-md rounded-xl border border-border bg-surface shadow-lg">
        <div className="flex items-center justify-between border-b border-border px-inline-lg py-stack-md">
          <Text as="h2" className="text-heading-sm">
            {t('document.void.title')}
          </Text>
          <button type="button" onClick={onClose} className="text-muted hover:text-foreground">
            ✕
          </button>
        </div>

        <form
          onSubmit={(e) => {
            void onSubmit(e);
          }}
          className="p-inline-lg"
        >
          <Stack gap="md">
            <Text tone="muted" className="text-body-sm">
              {t('document.void.description')}
            </Text>

            <div className="rounded-md border border-warning bg-warning-muted p-stack-sm">
              <Text className="text-label-sm text-warning">{t('document.void.warning')}</Text>
            </div>

            <div className="flex flex-col gap-stack-xs">
              <label className="text-label-sm font-medium">
                {t('document.void.reason_label')}
                <span className="ml-1 text-danger text-label-xs">
                  {t('common.required_marker')}
                </span>
              </label>
              <Input
                type="text"
                placeholder={t('document.void.reason_placeholder')}
                {...register('void_reason')}
              />
              {errors.void_reason !== undefined && (
                <Text tone="danger" className="text-label-xs">
                  {t('common.required_marker')}
                </Text>
              )}
            </div>

            <div className="flex flex-col gap-stack-xs">
              <label className="text-label-sm font-medium">{t('document.void.note_label')}</label>
              <textarea
                {...register('void_note')}
                rows={3}
                className="rounded-md border border-border bg-surface px-inline-sm py-stack-xs text-body-sm focus:outline-none focus:ring-2 focus:ring-brand resize-none"
              />
            </div>

            {submitError !== null && (
              <Text tone="danger" className="text-body-sm">
                {t(submitError)}
              </Text>
            )}

            <div className="flex justify-end gap-inline-md">
              <Button type="button" variant="secondary" onClick={onClose} disabled={isSubmitting}>
                {t('common.buttons.cancel')}
              </Button>
              <Button type="submit" variant="danger" disabled={isSubmitting}>
                {isSubmitting ? t('common.status.processing') : t('document.void.confirm_button')}
              </Button>
            </div>
          </Stack>
        </form>
      </div>
    </div>
  );
}
