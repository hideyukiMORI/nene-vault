import type { UseFormReturn } from 'react-hook-form';
import { useTranslation } from '@/shared/i18n/use-translation';
import { Button } from '@/shared/ui/primitives/Button';
import { Checkbox } from '@/shared/ui/primitives/Checkbox';
import { Field } from '@/shared/ui/components/Field';
import { Input } from '@/shared/ui/primitives/Input';
import { Select } from '@/shared/ui/primitives/Select';
import type { SearchFormValues } from '../model/use-document-search';

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
      className="card p-4.5 space-y-4"
    >
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <Field label={t('document.search.date_from_label')}>
          <Input type="date" {...register('transaction_date_from')} />
        </Field>
        <Field label={t('document.search.date_to_label')}>
          <Input type="date" {...register('transaction_date_to')} />
        </Field>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <Field label={t('document.search.amount_min_label')}>
          <Input type="number" placeholder="0" {...register('amount_min')} />
        </Field>
        <Field label={t('document.search.amount_max_label')}>
          <Input type="number" placeholder="0" {...register('amount_max')} />
        </Field>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <Field label={t('document.search.counterparty_label')}>
          <Input
            type="text"
            placeholder={t('document.upload.counterparty_placeholder')}
            {...register('counterparty_name')}
          />
        </Field>
        <Field label={t('document.search.category_label')}>
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

      <div className="flex items-center justify-between flex-wrap gap-3.5">
        <Checkbox
          label={t('document.search.include_voided_label')}
          {...register('include_voided')}
        />
        <div className="flex items-center gap-2">
          <Button type="button" variant="secondary" onClick={onReset}>
            {t('document.search.reset_button')}
          </Button>
          <Button type="submit" variant="primary" disabled={isLoading}>
            {t('document.search.search_button')}
          </Button>
        </div>
      </div>
    </form>
  );
}
