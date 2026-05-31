import { useTranslation } from '@/shared/i18n/use-translation';
import { Button, Field, Input, Modal, Stack, Text, Textarea } from '@/shared/ui';
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
    <Modal title={t('document.void.title')} onClose={onClose}>
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

          <Field
            label={t('document.void.reason_label')}
            required
            error={errors.void_reason !== undefined ? t('common.required_marker') : undefined}
          >
            <Input
              type="text"
              placeholder={t('document.void.reason_placeholder')}
              {...register('void_reason')}
            />
          </Field>

          <Field label={t('document.void.note_label')}>
            <Textarea {...register('void_note')} />
          </Field>

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
    </Modal>
  );
}
