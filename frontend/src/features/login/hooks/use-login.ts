import { zodResolver } from '@hookform/resolvers/zod';
import type { BaseSyntheticEvent } from 'react';
import { useForm, type UseFormRegister } from 'react-hook-form';
import { z } from 'zod';
import { useLogin as useLoginMutation, type AuthSession } from '@/entities/auth';
import { messageKeyForError } from '@/shared/i18n/map-problem-details';
import { useTranslation } from '@/shared/i18n/use-translation';

const loginSchema = z.object({
  email: z.string().email(),
  password: z.string().min(1),
});

export type LoginFormValues = z.infer<typeof loginSchema>;

interface UseLoginPageResult {
  register: UseFormRegister<LoginFormValues>;
  /** RHF-validated submit wrapper bound to the form element. */
  handleSubmit: (onSuccess: (session: AuthSession) => void) => (e?: BaseSyntheticEvent) => void;
  /** Direct submit (bypasses RHF validation) — used by hook tests. */
  submit: (values: LoginFormValues, onSuccess: (session: AuthSession) => void) => void;
  emailError: boolean;
  passwordError: boolean;
  submitError: string | null;
  isSubmitting: boolean;
}

export function useLoginPage(): UseLoginPageResult {
  const { t } = useTranslation();
  const mutation = useLoginMutation();
  const form = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
  });

  const submit = (values: LoginFormValues, onSuccess: (session: AuthSession) => void): void => {
    mutation.mutate(values, { onSuccess });
  };

  const handleSubmit = (onSuccess: (session: AuthSession) => void) =>
    form.handleSubmit((values) => {
      submit(values, onSuccess);
    });

  const messageKey = messageKeyForError(mutation.error);
  const submitError = messageKey !== null ? t(messageKey) : null;

  return {
    register: form.register,
    handleSubmit,
    submit,
    emailError: form.formState.errors.email !== undefined,
    passwordError: form.formState.errors.password !== undefined,
    submitError,
    isSubmitting: mutation.isPending,
  };
}
