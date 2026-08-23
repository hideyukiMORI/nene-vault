import { FormField, Input, Stack, Textarea } from '@hideyukimori/nene2-ui';
import { useTranslation } from '@/shared/i18n/use-translation';
import { fieldErrorText } from '@/shared/i18n/validation-keys';
import { Button } from '@/shared/ui/primitives/Button';
import { Callout } from '@/shared/ui/components/Callout';
import { Modal } from '@/shared/ui/components/Modal';
import { useVoidDocumentForm } from '../model/use-void-document';

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
    <Modal
      title={t('document.void.title')}
      onClose={onClose}
      closeLabel={t('common.buttons.close')}
    >
      <form
        onSubmit={(e) => {
          void onSubmit(e);
        }}
        className="p-x-lg"
      >
        <Stack gap="sm">
          <p className="text-text-muted body-sm">{t('document.void.description')}</p>

          <Callout tone="warn">{t('document.void.warning')}</Callout>

          <FormField
            id="void-reason"
            label={t('document.void.reason_label')}
            required
            requiredMarker={t('common.required_marker')}
            error={fieldErrorText(t, errors.void_reason)}
          >
            <Input
              type="text"
              placeholder={t('document.void.reason_placeholder')}
              {...register('void_reason')}
            />
          </FormField>

          <FormField id="void-note" label={t('document.void.note_label')}>
            <Textarea {...register('void_note')} />
          </FormField>

          {submitError !== null && <p className="text-2xs text-danger">{t(submitError)}</p>}

          <Stack
            direction="horizontal"
            align="center"
            justify="end"
            gap="2xs"
            className="max-md:flex-col-reverse max-md:items-stretch max-md:gap-2.5"
          >
            <Button type="button" variant="secondary" onClick={onClose} disabled={isSubmitting}>
              {t('common.buttons.cancel')}
            </Button>
            <Button type="submit" variant="danger" disabled={isSubmitting}>
              {isSubmitting ? t('common.status.processing') : t('document.void.confirm_button')}
            </Button>
          </Stack>
        </Stack>
      </form>
    </Modal>
  );
}
