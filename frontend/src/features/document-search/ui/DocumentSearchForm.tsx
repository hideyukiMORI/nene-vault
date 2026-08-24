import type { UseFormReturn } from 'react-hook-form';
import { Button, Checkbox, FormField, Grid, Input, Select, Stack } from '@hideyukimori/nene2-ui';
import { useTranslation } from '@/shared/i18n/use-translation';
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
      /* Not the kit's Card: it renders a `<div>` and this is the search form itself.
         The four declarations of the retired `.card` rule are spelled out instead — the
         same drain the class would have had, done at the one call site that cannot take
         the component (#417). */
      className="bg-surface-raised border border-border rounded-md shadow-sm p-x-md"
    >
      <Stack gap="sm">
        <Grid cols={{ base: 1, sm: 2 }} gap="sm">
          <FormField id="search-date-from" label={t('document.search.date_from_label')}>
            <Input type="date" {...register('transaction_date_from')} />
          </FormField>
          <FormField id="search-date-to" label={t('document.search.date_to_label')}>
            <Input type="date" {...register('transaction_date_to')} />
          </FormField>
        </Grid>

        <Grid cols={{ base: 1, sm: 2 }} gap="sm">
          <FormField id="search-amount-min" label={t('document.search.amount_min_label')}>
            <Input type="number" placeholder="0" {...register('amount_min')} />
          </FormField>
          <FormField id="search-amount-max" label={t('document.search.amount_max_label')}>
            <Input type="number" placeholder="0" {...register('amount_max')} />
          </FormField>
        </Grid>

        <Grid cols={{ base: 1, sm: 2 }} gap="sm">
          <FormField id="search-counterparty" label={t('document.search.counterparty_label')}>
            <Input
              type="text"
              placeholder={t('document.upload.counterparty_placeholder')}
              {...register('counterparty_name')}
            />
          </FormField>
          <FormField id="search-category" label={t('document.search.category_label')}>
            <Select {...register('category')}>
              <option value="">{t('common.none')}</option>
              {CATEGORIES.map((cat) => (
                <option key={cat} value={cat}>
                  {t(`document.category.${cat}`)}
                </option>
              ))}
            </Select>
          </FormField>
        </Grid>

        <Stack
          direction="horizontal"
          align="center"
          justify="between"
          wrap
          gap="sm"
          className="gap-3.5"
        >
          <Checkbox
            label={t('document.search.include_voided_label')}
            {...register('include_voided')}
          />
          <Stack direction="horizontal" align="center" gap="2xs">
            <Button type="button" variant="secondary" onClick={onReset}>
              {t('document.search.reset_button')}
            </Button>
            <Button type="submit" variant="primary" disabled={isLoading}>
              {t('document.search.search_button')}
            </Button>
          </Stack>
        </Stack>
      </Stack>
    </form>
  );
}
