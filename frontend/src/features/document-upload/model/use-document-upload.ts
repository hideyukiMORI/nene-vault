import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useUploadDocument } from '@/entities/document';
import { messageKeyForError } from '@/shared/i18n/map-problem-details';

const uploadSchema = z.object({
  file: z.instanceof(FileList).refine((list) => list.length > 0, 'required'),
  transaction_date: z.string().optional(),
  amount_cents: z.string().optional(),
  counterparty_name: z.string().min(1),
  category: z.enum(['invoice_received', 'contract', 'receipt', 'delivery_note', 'other']),
  tags: z.string().optional(),
});

export type UploadFormValues = z.infer<typeof uploadSchema>;

export function useDocumentUpload(onSuccess: () => void) {
  const mutation = useUploadDocument(onSuccess);

  const form = useForm<UploadFormValues>({
    resolver: zodResolver(uploadSchema),
    defaultValues: {
      transaction_date: '',
      amount_cents: '',
      counterparty_name: '',
      category: 'invoice_received',
      tags: '',
    },
  });

  const submitError =
    mutation.error !== null
      ? (messageKeyForError(mutation.error) ?? 'problem.internal_server_error')
      : null;

  return {
    form,
    onSubmit: form.handleSubmit((values) => {
      const file = values.file[0];
      if (file === undefined) return;
      mutation.mutate({
        file,
        counterparty_name: values.counterparty_name,
        category: values.category,
        transaction_date: values.transaction_date,
        amount_cents: values.amount_cents,
        tags: values.tags,
      });
    }),
    isSubmitting: mutation.isPending,
    submitError,
  };
}
