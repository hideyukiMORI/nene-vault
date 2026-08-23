import type { AuthSession } from '@/shared/api/auth-session';
import { SUPPORTED_LOCALES, type SupportedLocale } from '@/shared/i18n/locales';
import { useTranslation } from '@/shared/i18n/use-translation';
import { FormField, Input, Stack } from '@hideyukimori/nene2-ui';
import { BrandMark } from '@/shared/ui/primitives/BrandMark';
import { Button } from '@/shared/ui/primitives/Button';
import { LanguageSwitcher } from '@/shared/ui/components/LanguageSwitcher';
import { useLoginPage } from '../model/use-login';

export interface LoginFormProps {
  onLoggedIn: (session: AuthSession) => void;
}

export function LoginForm({ onLoggedIn }: LoginFormProps) {
  const { t, locale, setLocale } = useTranslation();
  const { register, handleSubmit, emailError, passwordError, submitError, isSubmitting } =
    useLoginPage();

  return (
    <div className="min-h-screen flex flex-col page-glow">
      <div className="flex justify-end px-6 py-4.5">
        <LanguageSwitcher
          label={t('navigation.language')}
          locale={locale}
          onLocaleChange={(next) => {
            setLocale(next as SupportedLocale);
          }}
          locales={SUPPORTED_LOCALES}
        />
      </div>
      <form className="center-card" onSubmit={handleSubmit(onLoggedIn)} noValidate>
        <div className="head">
          <div className="inline-flex flex-col items-center gap-3">
            <BrandMark size={46} className="text-x-seal" title="NeNe Vault" />
            <div className="font-serif text-2xl font-semibold text-x-ink-deep leading-brand tracking-wordmark whitespace-nowrap">
              NeNe <span className="text-x-brass">Vault</span>
            </div>
          </div>
        </div>
        <Stack className="body" gap="sm">
          <FormField id="login-email" label={t('auth.login.email_label')} error={emailError}>
            <Input
              type="email"
              autoComplete="username"
              placeholder={t('auth.login.email_placeholder')}
              {...register('email')}
            />
          </FormField>

          <FormField
            id="login-password"
            label={t('auth.login.password_label')}
            error={passwordError}
          >
            <Input
              type="password"
              autoComplete="current-password"
              placeholder={t('auth.login.password_placeholder')}
              {...register('password')}
            />
          </FormField>

          {submitError !== null && <p className="text-2xs text-danger">{submitError}</p>}

          <Button type="submit" disabled={isSubmitting} className="w-full">
            {isSubmitting ? t('auth.login.logging_in') : t('auth.login.submit')}
          </Button>
        </Stack>
      </form>
    </div>
  );
}
