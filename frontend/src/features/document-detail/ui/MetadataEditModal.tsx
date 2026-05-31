import { useTranslation } from '@/shared/i18n/use-translation';
import { Button, Field, Input, Modal, Select, Stack, Text } from '@/shared/ui';
import type { VaultDocument } from '@/entities/document';
import { useMetadataEditForm } from '../hooks/use-metadata-edit';
import type { OcrPrefill } from '../hooks/use-metadata-edit';

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
        className="p-inline-lg"
      >
        <Stack gap="md">
          <Text tone="muted" className="text-body-sm">
            {t('document.metadata_edit.description')}
          </Text>

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

          <div className="grid grid-cols-2 gap-inline-md">
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

          {submitError !== null && (
            <Text tone="danger" className="text-body-sm">
              {t(submitError)}
            </Text>
          )}

          <div className="flex justify-end gap-inline-md">
            <Button type="button" variant="secondary" onClick={onClose} disabled={isSubmitting}>
              {t('common.buttons.cancel')}
            </Button>
            <Button type="submit" variant="primary" disabled={isSubmitting}>
              {isSubmitting ? t('common.status.saving') : t('document.metadata_edit.save_button')}
            </Button>
          </div>
        </Stack>
      </form>
    </Modal>
  );
}
