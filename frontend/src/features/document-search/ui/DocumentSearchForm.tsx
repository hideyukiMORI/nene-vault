import type { UseFormReturn } from 'react-hook-form';
import { useTranslation } from '@/shared/i18n/use-translation';
import { Button, Input, Stack } from '@/shared/ui';
import type { SearchFormValues } from '../hooks/use-document-search';

interface DocumentSearchFormProps {
  form: UseFormReturn<SearchFormValues>;
  onSubmit: (e?: React.BaseSyntheticEvent) => Promise<void>;
  onReset: () => void;
  isLoading: boolean;
}

const CATEGORIES = ['invoice_received', 'contract', 'receipt', 'delivery_note', 'other'] as const;

export function DocumentSearchForm({
  form,
  onSubmit,
  onReset,
  isLoading,
}: DocumentSearchFormProps) {
  const { t } = useTranslation();
  const { register } = form;

  return (
    <form
      onSubmit={(e) => {
        void onSubmit(e);
      }}
      className="rounded-lg border border-border bg-surface-raised p-stack-md"
    >
      <Stack gap="md">
        <div className="grid grid-cols-2 gap-inline-md">
          <div className="flex flex-col gap-stack-xs">
            <label className="text-label-sm text-muted">
              {t('document.search.date_from_label')}
            </label>
            <Input type="date" {...register('transaction_date_from')} />
          </div>
          <div className="flex flex-col gap-stack-xs">
            <label className="text-label-sm text-muted">{t('document.search.date_to_label')}</label>
            <Input type="date" {...register('transaction_date_to')} />
          </div>
        </div>

        <div className="grid grid-cols-2 gap-inline-md">
          <div className="flex flex-col gap-stack-xs">
            <label className="text-label-sm text-muted">
              {t('document.search.amount_min_label')}
            </label>
            <Input type="number" placeholder="0" {...register('amount_min')} />
          </div>
          <div className="flex flex-col gap-stack-xs">
            <label className="text-label-sm text-muted">
              {t('document.search.amount_max_label')}
            </label>
            <Input type="number" placeholder="0" {...register('amount_max')} />
          </div>
        </div>

        <div className="flex flex-col gap-stack-xs">
          <label className="text-label-sm text-muted">
            {t('document.search.counterparty_label')}
          </label>
          <Input
            type="text"
            placeholder={t('document.upload.counterparty_placeholder')}
            {...register('counterparty_name')}
          />
        </div>

        <div className="flex items-center gap-inline-lg">
          <div className="flex flex-col gap-stack-xs flex-1">
            <label className="text-label-sm text-muted">
              {t('document.search.category_label')}
            </label>
            <select
              {...register('category')}
              className="h-10 rounded-md border border-border bg-surface px-inline-sm text-body-sm focus:outline-none focus:ring-2 focus:ring-brand"
            >
              <option value="">{t('common.none')}</option>
              {CATEGORIES.map((cat) => (
                <option key={cat} value={cat}>
                  {t(`document.category.${cat}`)}
                </option>
              ))}
            </select>
          </div>

          <label className="flex items-center gap-inline-sm cursor-pointer mt-stack-lg">
            <input
              type="checkbox"
              {...register('include_voided')}
              className="h-4 w-4 rounded border-border text-brand focus:ring-brand"
            />
            <span className="text-body-sm">{t('document.search.include_voided_label')}</span>
          </label>
        </div>

        <div className="flex gap-inline-md">
          <Button type="submit" variant="primary" disabled={isLoading}>
            {t('document.search.search_button')}
          </Button>
          <Button type="button" variant="secondary" onClick={onReset}>
            {t('document.search.reset_button')}
          </Button>
        </div>
      </Stack>
    </form>
  );
}
