import { Button, FormField, Grid, Input, Modal, Select, Stack } from '@hideyukimori/nene2-ui';
import { useTranslation } from '@/shared/i18n/use-translation';
import { fieldErrorText } from '@/shared/i18n/validation-keys';
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
  const {
    register,
    formState: { errors },
  } = form;

  return (
    <Modal
      open
      header
      title={t('document.metadata_edit.title')}
      onClose={onClose}
      size="md"
      closeLabel={t('common.buttons.close')}
    >
      <form
        onSubmit={(e) => {
          void onSubmit(e);
        }}
        className="p-x-lg"
      >
        <Stack gap="sm">
          <p className="text-text-muted body-sm">{t('document.metadata_edit.description')}</p>

          <FormField
            id="metadata-counterparty-name"
            label={t('document.metadata.counterparty_name')}
            required
            requiredMarker={t('common.required_marker')}
            error={fieldErrorText(t, errors.counterparty_name)}
          >
            <Input type="text" {...register('counterparty_name')} />
          </FormField>

          <FormField id="metadata-category" label={t('document.metadata.category')}>
            <Select {...register('category')}>
              {CATEGORIES.map((cat) => (
                <option key={cat} value={cat}>
                  {t(`document.category.${cat}`)}
                </option>
              ))}
            </Select>
          </FormField>

          <Grid cols={{ base: 1, sm: 2 }} gap="sm">
            <FormField
              id="metadata-transaction-date"
              label={t('document.metadata.transaction_date')}
            >
              <Input type="date" {...register('transaction_date')} />
            </FormField>
            <FormField id="metadata-amount-cents" label={t('document.metadata.amount_cents')}>
              <Input type="number" placeholder="0" {...register('amount_cents')} />
            </FormField>
          </Grid>

          <FormField id="metadata-tags" label={t('document.metadata.tags')}>
            <Input
              type="text"
              placeholder={t('document.upload.tags_placeholder')}
              {...register('tags')}
            />
          </FormField>

          {submitError !== null && <p className="text-2xs text-danger">{t(submitError)}</p>}

          <Stack
            direction="horizontal"
            align="center"
            justify="end"
            gap="2xs"
            className="max-md:flex-col-reverse max-md:items-stretch max-md:gap-2.5"
          >
            <Button
              type="button"
              variant="outline"
              tone="neutral"
              onClick={onClose}
              disabled={isSubmitting}
            >
              {t('common.buttons.cancel')}
            </Button>
            <Button type="submit" disabled={isSubmitting}>
              {isSubmitting ? t('common.status.saving') : t('document.metadata_edit.save_button')}
            </Button>
          </Stack>
        </Stack>
      </form>
    </Modal>
  );
}
