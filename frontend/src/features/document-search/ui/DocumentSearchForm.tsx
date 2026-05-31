import type { UseFormReturn } from 'react-hook-form';
import { useTranslation } from '@/shared/i18n/use-translation';
import { Button, Field, Input, Select, Stack } from '@/shared/ui';
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
          <Field label={t('document.search.date_from_label')} labelTone="muted">
            <Input type="date" {...register('transaction_date_from')} />
          </Field>
          <Field label={t('document.search.date_to_label')} labelTone="muted">
            <Input type="date" {...register('transaction_date_to')} />
          </Field>
        </div>

        <div className="grid grid-cols-2 gap-inline-md">
          <Field label={t('document.search.amount_min_label')} labelTone="muted">
            <Input type="number" placeholder="0" {...register('amount_min')} />
          </Field>
          <Field label={t('document.search.amount_max_label')} labelTone="muted">
            <Input type="number" placeholder="0" {...register('amount_max')} />
          </Field>
        </div>

        <Field label={t('document.search.counterparty_label')} labelTone="muted">
          <Input
            type="text"
            placeholder={t('document.upload.counterparty_placeholder')}
            {...register('counterparty_name')}
          />
        </Field>

        <div className="flex items-center gap-inline-lg">
          <div className="flex-1">
            <Field label={t('document.search.category_label')} labelTone="muted">
              <Select {...register('category')}>
                <option value="">{t('common.none')}</option>
                {CATEGORIES.map((cat) => (
                  <option key={cat} value={cat}>
                    {t(`document.category.${cat}`)}
                  </option>
                ))}
              </Select>
            </Field>
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
