import { useTranslation } from '@/shared/i18n/use-translation';
import { Button, Input, Stack, Text } from '@/shared/ui';
import type { VaultDocument } from '@/entities/document';
import { useMetadataEditForm } from '../hooks/use-metadata-edit';

const CATEGORIES = ['invoice_received', 'contract', 'receipt', 'delivery_note', 'other'] as const;

interface MetadataEditModalProps {
  doc: VaultDocument;
  onClose: () => void;
}

export function MetadataEditModal({ doc, onClose }: MetadataEditModalProps) {
  const { t } = useTranslation();
  const { form, onSubmit, isSubmitting, submitError } = useMetadataEditForm(doc, onClose);
  const { register } = form;

  return (
    <div
      role="dialog"
      aria-modal="true"
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    >
      <div className="w-full max-w-lg rounded-xl border border-border bg-surface shadow-lg">
        <div className="flex items-center justify-between border-b border-border px-inline-lg py-stack-md">
          <Text as="h2" className="text-heading-sm">
            {t('document.metadata_edit.title')}
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
              {t('document.metadata_edit.description')}
            </Text>

            <div className="flex flex-col gap-stack-xs">
              <label className="text-label-sm font-medium">
                {t('document.metadata.counterparty_name')}
                <span className="ml-1 text-danger text-label-xs">
                  {t('common.required_marker')}
                </span>
              </label>
              <Input type="text" {...register('counterparty_name')} />
            </div>

            <div className="flex flex-col gap-stack-xs">
              <label className="text-label-sm font-medium">{t('document.metadata.category')}</label>
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
                  {t('document.metadata.transaction_date')}
                </label>
                <Input type="date" {...register('transaction_date')} />
              </div>
              <div className="flex flex-col gap-stack-xs">
                <label className="text-label-sm font-medium">
                  {t('document.metadata.amount_cents')}
                </label>
                <Input type="number" placeholder="0" {...register('amount_cents')} />
              </div>
            </div>

            <div className="flex flex-col gap-stack-xs">
              <label className="text-label-sm font-medium">{t('document.metadata.tags')}</label>
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
      </div>
    </div>
  );
}
