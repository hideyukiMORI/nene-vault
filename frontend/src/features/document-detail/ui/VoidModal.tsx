import { useTranslation } from '@/shared/i18n/use-translation';
import { Button } from '@/shared/ui/primitives/Button';
import { Callout } from '@/shared/ui/components/Callout';
import { Field } from '@/shared/ui/components/Field';
import { Input } from '@/shared/ui/primitives/Input';
import { Modal } from '@/shared/ui/components/Modal';
import { Textarea } from '@/shared/ui/primitives/Textarea';
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
        className="modal-body stack-md"
      >
        <p className="muted body-sm">{t('document.void.description')}</p>

        <Callout tone="warn">{t('document.void.warning')}</Callout>

        <Field
          label={t('document.void.reason_label')}
          required
          requiredMarker={t('common.required_marker')}
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

        {submitError !== null && <p className="field-error">{t(submitError)}</p>}

        <div className="row end gap-sm">
          <Button type="button" variant="secondary" onClick={onClose} disabled={isSubmitting}>
            {t('common.buttons.cancel')}
          </Button>
          <Button type="submit" variant="danger" disabled={isSubmitting}>
            {isSubmitting ? t('common.status.processing') : t('document.void.confirm_button')}
          </Button>
        </div>
      </form>
    </Modal>
  );
}
