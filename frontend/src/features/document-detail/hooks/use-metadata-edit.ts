import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useUpdateDocumentMetadata } from '@/entities/document';
import type { VaultDocument } from '@/entities/document';
import { messageKeyForError } from '@/shared/i18n/map-problem-details';

const metadataSchema = z.object({
  transaction_date: z.string().optional(),
  amount_cents: z.string().optional(),
  counterparty_name: z.string().min(1),
  category: z.enum(['invoice_received', 'contract', 'receipt', 'delivery_note', 'other']),
  tags: z.string().optional(),
});

export type MetadataFormValues = z.infer<typeof metadataSchema>;

function tagsToString(tags: string[] | undefined): string {
  return (tags ?? []).join(', ');
}

export function useMetadataEditForm(doc: VaultDocument, onSuccess: () => void) {
  const mutation = useUpdateDocumentMetadata(onSuccess);

  const form = useForm<MetadataFormValues>({
    resolver: zodResolver(metadataSchema),
    defaultValues: {
      transaction_date: doc.transaction_date ?? '',
      amount_cents: doc.amount_cents !== null ? String(doc.amount_cents) : '',
      counterparty_name: doc.counterparty_name,
      category: doc.category,
      tags: tagsToString(doc.tags),
    },
  });

  const submitError =
    mutation.error !== null
      ? (messageKeyForError(mutation.error) ?? 'problem.internal_server_error')
      : null;

  return {
    form,
    onSubmit: form.handleSubmit((values) => {
      const amountRaw = parseInt(values.amount_cents ?? '', 10);
      mutation.mutate({
        id: doc.id,
        transaction_date: values.transaction_date !== '' ? values.transaction_date : null,
        amount_cents: !isNaN(amountRaw) ? amountRaw : null,
        counterparty_name: values.counterparty_name,
        category: values.category,
        tags:
          values.tags !== '' && values.tags !== undefined
            ? values.tags
                .split(',')
                .map((t) => t.trim())
                .filter((t) => t !== '')
            : [],
      });
    }),
    isSubmitting: mutation.isPending,
    submitError,
  };
}
