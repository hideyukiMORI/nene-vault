import { useTranslation } from '@/shared/i18n/use-translation';
import { Button, Input, Stack, Text } from '@/shared/ui';
import { useDocumentUpload } from '../hooks/use-document-upload';

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

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label={t('document.upload.title')}
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    >
      <div className="w-full max-w-lg rounded-xl border border-border bg-surface shadow-lg">
        <div className="flex items-center justify-between border-b border-border px-inline-lg py-stack-md">
          <Text as="h2" className="text-heading-sm">
            {t('document.upload.title')}
          </Text>
          <button
            type="button"
            onClick={onClose}
            className="text-muted hover:text-foreground"
            aria-label={t('common.buttons.close')}
          >
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
            <div className="flex flex-col gap-stack-xs">
              <label className="text-label-sm font-medium">
                {t('document.upload.file_label')}
                <span className="ml-1 text-danger text-label-xs">
                  {t('common.required_marker')}
                </span>
              </label>
              <input
                type="file"
                accept=".pdf,.jpg,.jpeg,.png"
                {...register('file')}
                className="block w-full text-body-sm text-muted file:mr-inline-md file:rounded-md file:border-0 file:bg-brand file:px-inline-md file:py-stack-xs file:text-label-sm file:font-medium file:text-on-brand hover:file:bg-brand-hover"
              />
              <Text tone="muted" className="text-label-xs">
                {t('document.upload.file_hint')}
              </Text>
              {errors.file !== undefined && (
                <Text tone="danger" className="text-label-xs">
                  {t('common.required_marker')}
                </Text>
              )}
            </div>

            <div className="flex flex-col gap-stack-xs">
              <label className="text-label-sm font-medium">
                {t('document.upload.counterparty_label')}
                <span className="ml-1 text-danger text-label-xs">
                  {t('common.required_marker')}
                </span>
              </label>
              <Input
                type="text"
                placeholder={t('document.upload.counterparty_placeholder')}
                {...register('counterparty_name')}
              />
              {errors.counterparty_name !== undefined && (
                <Text tone="danger" className="text-label-xs">
                  {t('common.required_marker')}
                </Text>
              )}
            </div>

            <div className="flex flex-col gap-stack-xs">
              <label className="text-label-sm font-medium">
                {t('document.upload.category_label')}
                <span className="ml-1 text-danger text-label-xs">
                  {t('common.required_marker')}
                </span>
              </label>
              <select
                {...register('category')}
                className="h-10 rounded-md border border-border bg-surface px-inline-sm text-body-sm focus:outline-none focus:ring-2 focus:ring-brand"
              >
                {CATEGORIES.map((cat) => (
                  <option key={cat} value={cat}>
                    {t(`document.category.${cat}`)}
                  </option>
                ))}
              </select>
            </div>

            <div className="grid grid-cols-2 gap-inline-md">
              <div className="flex flex-col gap-stack-xs">
                <label className="text-label-sm font-medium">
                  {t('document.upload.transaction_date_label')}
                </label>
                <Input type="date" {...register('transaction_date')} />
                <Text tone="muted" className="text-label-xs">
                  {t('document.upload.transaction_date_hint')}
                </Text>
              </div>

              <div className="flex flex-col gap-stack-xs">
                <label className="text-label-sm font-medium">
                  {t('document.upload.amount_label')}
                </label>
                <Input
                  type="number"
                  placeholder={t('document.upload.amount_placeholder')}
                  {...register('amount_cents')}
                />
                <Text tone="muted" className="text-label-xs">
                  {t('document.upload.amount_hint')}
                </Text>
              </div>
            </div>

            <div className="flex flex-col gap-stack-xs">
              <label className="text-label-sm font-medium">{t('document.upload.tags_label')}</label>
              <Input
                type="text"
                placeholder={t('document.upload.tags_placeholder')}
                {...register('tags')}
              />
            </div>

            {submitError !== null && (
              <Text tone="danger" className="text-body-sm">
                {t(submitError)}
              </Text>
            )}

            <div className="flex justify-end gap-inline-md pt-stack-sm">
              <Button type="button" variant="secondary" onClick={onClose} disabled={isSubmitting}>
                {t('common.buttons.cancel')}
              </Button>
              <Button type="submit" variant="primary" disabled={isSubmitting}>
                {isSubmitting ? t('common.status.uploading') : t('document.upload.submit')}
              </Button>
            </div>
          </Stack>
        </form>
      </div>
    </div>
  );
}
