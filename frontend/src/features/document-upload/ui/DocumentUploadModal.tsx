import { Button, FormField, Grid, Input, Modal, Select, Stack } from '@hideyukimori/nene2-ui';
import { env } from '@/shared/config/env';
import { useTranslation } from '@/shared/i18n/use-translation';
import { fieldErrorText } from '@/shared/i18n/validation-keys';
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
    <Modal
      open
      header
      title={t('document.upload.title')}
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
          <FormField
            id="upload-file"
            label={t('document.upload.file_label')}
            required
            requiredMarker={requiredMarker}
            hint={t('document.upload.file_hint', { max_size_mb: env.uploadMaxFileSizeMb })}
            error={fieldErrorText(t, errors.file)}
          >
            <input
              type="file"
              accept=".pdf,.jpg,.jpeg,.png"
              // Regenerated from `.file-input` (+ its ::file-selector-button rules), #361.
              className="text-sm leading-inherit text-text-muted w-full file:text-xs file:leading-inherit file:font-semibold file:mr-3 file:border-0 file:rounded-sm file:bg-accent file:text-on-accent file:py-1.75 file:px-3.25 file:cursor-pointer hover:file:bg-accent-hover"
              {...register('file')}
            />
          </FormField>

          <FormField
            id="upload-counterparty"
            label={t('document.upload.counterparty_label')}
            required
            requiredMarker={requiredMarker}
            error={fieldErrorText(t, errors.counterparty_name)}
          >
            <Input
              type="text"
              placeholder={t('document.upload.counterparty_placeholder')}
              {...register('counterparty_name')}
            />
          </FormField>

          <FormField
            id="upload-category"
            label={t('document.upload.category_label')}
            required
            requiredMarker={requiredMarker}
          >
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
              id="upload-transaction-date"
              label={t('document.upload.transaction_date_label')}
              hint={t('document.upload.transaction_date_hint')}
            >
              <Input type="date" {...register('transaction_date')} />
            </FormField>
            <FormField
              id="upload-amount"
              label={t('document.upload.amount_label')}
              hint={t('document.upload.amount_hint')}
            >
              <Input
                type="number"
                placeholder={t('document.upload.amount_placeholder')}
                {...register('amount_cents')}
              />
            </FormField>
          </Grid>

          <FormField id="upload-tags" label={t('document.upload.tags_label')}>
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
            <Button type="button" variant="secondary" onClick={onClose} disabled={isSubmitting}>
              {t('common.buttons.cancel')}
            </Button>
            <Button type="submit" variant="primary" disabled={isSubmitting}>
              {isSubmitting ? t('common.status.uploading') : t('document.upload.submit')}
            </Button>
          </Stack>
        </Stack>
      </form>
    </Modal>
  );
}
