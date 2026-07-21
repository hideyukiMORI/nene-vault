import { env } from '@/shared/config/env';
import { useTranslation } from '@/shared/i18n/use-translation';
import { Button } from '@/shared/ui/primitives/Button';
import { Field } from '@/shared/ui/components/Field';
import { Input } from '@/shared/ui/primitives/Input';
import { Modal } from '@/shared/ui/components/Modal';
import { Select } from '@/shared/ui/primitives/Select';
import { useDocumentUpload } from '../model/use-document-upload';

const CATEGORIES = ['invoice_received', 'contract', 'receipt', 'delivery_note', 'other'] as const;

interface DocumentUploadModalProps {
  onClose: () => void;
}

export function DocumentUploadModal({ onClose }: DocumentUploadModalProps) {
  const { t } = useTranslation();
  const { form, onSubmit, isSubmitting, submitError } = useDocumentUpload(onClose);
  const {
    register,
    formState: { errors },
  } = form;

  const requiredMarker = t('common.required_marker');

  return (
    <Modal title={t('document.upload.title')} onClose={onClose} size="md">
      <form
        onSubmit={(e) => {
          void onSubmit(e);
        }}
        className="modal-body stack-md"
      >
        <Field
          label={t('document.upload.file_label')}
          required
          hint={t('document.upload.file_hint', { max_size_mb: env.uploadMaxFileSizeMb })}
          error={errors.file !== undefined ? requiredMarker : undefined}
        >
          <input
            type="file"
            accept=".pdf,.jpg,.jpeg,.png"
            className="file-input"
            {...register('file')}
          />
        </Field>

        <Field
          label={t('document.upload.counterparty_label')}
          required
          error={errors.counterparty_name !== undefined ? requiredMarker : undefined}
        >
          <Input
            type="text"
            placeholder={t('document.upload.counterparty_placeholder')}
            {...register('counterparty_name')}
          />
        </Field>

        <Field label={t('document.upload.category_label')} required>
          <Select {...register('category')}>
            {CATEGORIES.map((cat) => (
              <option key={cat} value={cat}>
                {t(`document.category.${cat}`)}
              </option>
            ))}
          </Select>
        </Field>

        <div className="grid-2">
          <Field
            label={t('document.upload.transaction_date_label')}
            hint={t('document.upload.transaction_date_hint')}
          >
            <Input type="date" {...register('transaction_date')} />
          </Field>
          <Field label={t('document.upload.amount_label')} hint={t('document.upload.amount_hint')}>
            <Input
              type="number"
              placeholder={t('document.upload.amount_placeholder')}
              {...register('amount_cents')}
            />
          </Field>
        </div>

        <Field label={t('document.upload.tags_label')}>
          <Input
            type="text"
            placeholder={t('document.upload.tags_placeholder')}
            {...register('tags')}
          />
        </Field>

        {submitError !== null && <p className="field-error">{t(submitError)}</p>}

        <div className="row end gap-sm">
          <Button type="button" variant="secondary" onClick={onClose} disabled={isSubmitting}>
            {t('common.buttons.cancel')}
          </Button>
          <Button type="submit" variant="primary" disabled={isSubmitting}>
            {isSubmitting ? t('common.status.uploading') : t('document.upload.submit')}
          </Button>
        </div>
      </form>
    </Modal>
  );
}
