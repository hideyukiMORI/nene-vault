import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useVoidDocument } from '@/entities/document';
import { messageKeyForError } from '@/shared/i18n/map-problem-details';

const voidSchema = z.object({
  void_reason: z.string().min(1),
  void_note: z.string().optional(),
});

export type VoidFormValues = z.infer<typeof voidSchema>;

export function useVoidDocumentForm(id: string, onSuccess: () => void) {
  const mutation = useVoidDocument(onSuccess);

  const form = useForm<VoidFormValues>({
    resolver: zodResolver(voidSchema),
    defaultValues: { void_reason: '', void_note: '' },
  });

  const submitError =
    mutation.error !== null
      ? (messageKeyForError(mutation.error) ?? 'problem.internal_server_error')
      : null;

  return {
    form,
    onSubmit: form.handleSubmit((values) => {
      mutation.mutate({ id, ...values });
    }),
    isSubmitting: mutation.isPending,
    submitError,
  };
}
