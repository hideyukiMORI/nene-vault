import { useTranslation } from '@/shared/i18n/use-translation';
import { Button } from '@/shared/ui/primitives/Button';
import { Field } from '@/shared/ui/components/Field';
import { Input } from '@/shared/ui/primitives/Input';
import { Modal } from '@/shared/ui/components/Modal';
import { Select } from '@/shared/ui/primitives/Select';
import type { VaultDocument } from '@/entities/document';
import { useMetadataEditForm } from '../model/use-metadata-edit';
import type { OcrPrefill } from '../model/use-metadata-edit';

const CATEGORIES = ['invoice_received', 'contract', 'receipt', 'delivery_note', 'other'] as const;

interface MetadataEditModalProps {
  doc: VaultDocument;
  onClose: () => void;
  ocrPrefill?: OcrPrefill;
}

export function MetadataEditModal({ doc, onClose, ocrPrefill }: MetadataEditModalProps) {
  const { t } = useTranslation();
  const { form, onSubmit, isSubmitting, submitError } = useMetadataEditForm(
    doc,
    onClose,
    ocrPrefill,
  );
  const { register } = form;

  return (
    <Modal title={t('document.metadata_edit.title')} onClose={onClose} size="md">
      <form
        onSubmit={(e) => {
          void onSubmit(e);
        }}
        className="modal-body stack-md"
      >
        <p className="muted body-sm">{t('document.metadata_edit.description')}</p>

        <Field label={t('document.metadata.counterparty_name')} required>
          <Input type="text" {...register('counterparty_name')} />
        </Field>

        <Field label={t('document.metadata.category')}>
          <Select {...register('category')}>
            {CATEGORIES.map((cat) => (
              <option key={cat} value={cat}>
                {t(`document.category.${cat}`)}
              </option>
            ))}
          </Select>
        </Field>

        <div className="grid-2">
          <Field label={t('document.metadata.transaction_date')}>
            <Input type="date" {...register('transaction_date')} />
          </Field>
          <Field label={t('document.metadata.amount_cents')}>
            <Input type="number" placeholder="0" {...register('amount_cents')} />
          </Field>
        </div>

        <Field label={t('document.metadata.tags')}>
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
            {isSubmitting ? t('common.status.saving') : t('document.metadata_edit.save_button')}
          </Button>
        </div>
      </form>
    </Modal>
  );
}
