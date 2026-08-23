import { zodResolver } from '@hookform/resolvers/zod';
import type { BaseSyntheticEvent } from 'react';
import { useForm, type UseFormRegister } from 'react-hook-form';
import { z } from 'zod';
import { useLogin as useLoginMutation } from '@/entities/auth';
import type { AuthSession } from '@/shared/api/auth-session';
import { dynamicMessageKey } from '@/shared/i18n/catalogs';
import { messageKeyForError } from '@/shared/i18n/map-problem-details';
import { VALIDATION } from '@/shared/i18n/validation-keys';
import { useTranslation } from '@/shared/i18n/use-translation';

// Messages are locale *keys*, resolved by the hook. Zod carries no user-facing text:
// a schema is shared by every locale, and the field that fails is the only thing it knows.
const loginSchema = z.object({
  email: z.string().min(1, VALIDATION.required).email(VALIDATION.invalidEmail),
  password: z.string().min(1, VALIDATION.required),
});

export type LoginFormValues = z.infer<typeof loginSchema>;

interface UseLoginPageResult {
  register: UseFormRegister<LoginFormValues>;
  /** RHF-validated submit wrapper bound to the form element. */
  handleSubmit: (onSuccess: (session: AuthSession) => void) => (e?: BaseSyntheticEvent) => void;
  /** Direct submit (bypasses RHF validation) — used by hook tests. */
  submit: (values: LoginFormValues, onSuccess: (session: AuthSession) => void) => void;
  /**
   * Resolved message for the field, or null when it is valid.
   *
   * 🔴 A message, not a boolean. This used to be `emailError: boolean`, which is all the
   * screen needed to paint the border red — and it is why the login form marked fields
   * invalid without ever saying why (#385). A boolean cannot be rendered, so nothing was.
   */
  emailError: string | null;
  passwordError: string | null;
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

  const fieldError = (message: string | undefined): string | null =>
    message === undefined ? null : t(dynamicMessageKey(message));

  return {
    register: form.register,
    handleSubmit,
    submit,
    emailError: fieldError(form.formState.errors.email?.message),
    passwordError: fieldError(form.formState.errors.password?.message),
    submitError,
    isSubmitting: mutation.isPending,
  };
}
