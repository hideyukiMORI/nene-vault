import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useDocuments } from '@/entities/document';
import type { SearchDocumentsParams } from '@/entities/document';

const PAGE_SIZE = 20;

const searchSchema = z.object({
  transaction_date_from: z.string().optional(),
  transaction_date_to: z.string().optional(),
  amount_min: z.string().optional(),
  amount_max: z.string().optional(),
  counterparty_name: z.string().optional(),
  category: z.enum(['', 'invoice_received', 'contract', 'receipt', 'delivery_note', 'other']),
  include_voided: z.boolean(),
});

export type SearchFormValues = z.infer<typeof searchSchema>;

function toQueryParams(values: SearchFormValues, offset: number): SearchDocumentsParams {
  const params: SearchDocumentsParams = { limit: PAGE_SIZE, offset };
  if (values.transaction_date_from !== '' && values.transaction_date_from !== undefined) {
    params.transaction_date_from = values.transaction_date_from;
  }
  if (values.transaction_date_to !== '' && values.transaction_date_to !== undefined) {
    params.transaction_date_to = values.transaction_date_to;
  }
  const min = parseInt(values.amount_min ?? '', 10);
  if (!isNaN(min)) {
    params.amount_min_cents = min;
  }
  const max = parseInt(values.amount_max ?? '', 10);
  if (!isNaN(max)) {
    params.amount_max_cents = max;
  }
  if (values.counterparty_name !== '' && values.counterparty_name !== undefined) {
    params.counterparty_name = values.counterparty_name;
  }
  if (values.category !== '') {
    params.category = values.category;
  }
  if (values.include_voided) {
    params.include_voided = true;
  }
  return params;
}

export function useDocumentSearch() {
  const [committedValues, setCommittedValues] = useState<SearchFormValues>({
    transaction_date_from: '',
    transaction_date_to: '',
    amount_min: '',
    amount_max: '',
    counterparty_name: '',
    category: '',
    include_voided: false,
  });
  const [offset, setOffset] = useState(0);

  const form = useForm<SearchFormValues>({
    resolver: zodResolver(searchSchema),
    defaultValues: committedValues,
  });

  const queryParams = toQueryParams(committedValues, offset);
  const result = useDocuments(queryParams);

  function onSubmit(values: SearchFormValues) {
    setOffset(0);
    setCommittedValues(values);
  }

  function onReset() {
    const defaults: SearchFormValues = {
      transaction_date_from: '',
      transaction_date_to: '',
      amount_min: '',
      amount_max: '',
      counterparty_name: '',
      category: '',
      include_voided: false,
    };
    form.reset(defaults);
    setOffset(0);
    setCommittedValues(defaults);
  }

  const total = result.data?.total ?? 0;
  const currentPage = Math.floor(offset / PAGE_SIZE);
  const totalPages = Math.ceil(total / PAGE_SIZE);

  return {
    form,
    onSubmit: form.handleSubmit(onSubmit),
    onReset,
    result,
    pagination: {
      offset,
      limit: PAGE_SIZE,
      total,
      currentPage,
      totalPages,
      canPrev: offset > 0,
      canNext: offset + PAGE_SIZE < total,
      goNext: () => {
        setOffset((o) => o + PAGE_SIZE);
      },
      goPrev: () => {
        setOffset((o) => Math.max(0, o - PAGE_SIZE));
      },
    },
  };
}
