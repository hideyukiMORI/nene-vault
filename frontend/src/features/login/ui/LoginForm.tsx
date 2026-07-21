import type { AuthSession } from '@/shared/api/auth-session';
import { SUPPORTED_LOCALES, type SupportedLocale } from '@/shared/i18n/locales';
import { useTranslation } from '@/shared/i18n/use-translation';
import { BrandMark } from '@/shared/ui/primitives/BrandMark';
import { Button } from '@/shared/ui/primitives/Button';
import { Field } from '@/shared/ui/components/Field';
import { Input } from '@/shared/ui/primitives/Input';
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
    <div className="center">
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
            <div className="brand-name">
              NeNe <span className="text-x-brass">Vault</span>
            </div>
          </div>
        </div>
        <div className="body space-y-4">
          <Field label={t('auth.login.email_label')}>
            <Input
              type="email"
              autoComplete="username"
              aria-invalid={emailError}
              placeholder={t('auth.login.email_placeholder')}
              {...register('email')}
            />
          </Field>

          <Field label={t('auth.login.password_label')}>
            <Input
              type="password"
              autoComplete="current-password"
              aria-invalid={passwordError}
              placeholder={t('auth.login.password_placeholder')}
              {...register('password')}
            />
          </Field>

          {submitError !== null && <p className="text-2xs text-danger">{submitError}</p>}

          <Button type="submit" disabled={isSubmitting} className="w-full">
            {isSubmitting ? t('auth.login.logging_in') : t('auth.login.submit')}
          </Button>
        </div>
      </form>
    </div>
  );
}
