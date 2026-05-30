import type { AuthSession } from '@/entities/auth';
import { useTranslation } from '@/shared/i18n/use-translation';
import { Button, Input, Stack, Text } from '@/shared/ui';
import { LanguageSwitcher } from '@/shared/ui/components/LanguageSwitcher';
import { useLoginPage } from '../hooks/use-login';

export interface LoginFormProps {
  onLoggedIn: (session: AuthSession) => void;
}

export function LoginForm({ onLoggedIn }: LoginFormProps) {
  const { t } = useTranslation();
  const { register, handleSubmit, emailError, passwordError, submitError, isSubmitting } =
    useLoginPage();

  return (
    <div className="flex min-h-screen flex-col bg-surface">
      <header className="flex justify-end p-inline-md">
        <LanguageSwitcher />
      </header>
      <form
        className="m-auto w-full max-w-sm rounded-md border border-border bg-surface-raised p-inline-lg shadow-md"
        onSubmit={handleSubmit(onLoggedIn)}
        noValidate
      >
        <Stack gap="md">
          <Text as="h1" className="text-heading-md">
            {t('auth.login.title')}
          </Text>

          <label className="flex flex-col gap-stack-sm">
            <Text as="span" tone="muted">
              {t('auth.login.email_label')}
            </Text>
            <Input
              type="email"
              autoComplete="username"
              aria-invalid={emailError}
              placeholder={t('auth.login.email_placeholder')}
              {...register('email')}
            />
          </label>

          <label className="flex flex-col gap-stack-sm">
            <Text as="span" tone="muted">
              {t('auth.login.password_label')}
            </Text>
            <Input
              type="password"
              autoComplete="current-password"
              aria-invalid={passwordError}
              placeholder={t('auth.login.password_placeholder')}
              {...register('password')}
            />
          </label>

          {submitError !== null && <Text tone="danger">{submitError}</Text>}

          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting ? t('auth.login.logging_in') : t('auth.login.submit')}
          </Button>
        </Stack>
      </form>
    </div>
  );
}
