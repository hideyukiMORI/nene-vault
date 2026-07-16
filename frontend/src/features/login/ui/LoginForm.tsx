import type { AuthSession } from '@/entities/auth';
import { useTranslation } from '@/shared/i18n/use-translation';
import { BrandMark, Button, Field, Input } from '@/shared/ui';
import { LanguageSwitcher } from '@/shared/ui/components/LanguageSwitcher';
import { useLoginPage } from '../model/use-login';

export interface LoginFormProps {
  onLoggedIn: (session: AuthSession) => void;
}

export function LoginForm({ onLoggedIn }: LoginFormProps) {
  const { t } = useTranslation();
  const { register, handleSubmit, emailError, passwordError, submitError, isSubmitting } =
    useLoginPage();

  return (
    <div className="center">
      <div className="center-top">
        <LanguageSwitcher />
      </div>
      <form className="center-card" onSubmit={handleSubmit(onLoggedIn)} noValidate>
        <div className="head">
          <div className="brand-lock">
            <BrandMark size={46} className="text-x-seal" title="NeNe Vault" />
            <div className="brand-name">
              NeNe <span className="text-x-brass">Vault</span>
            </div>
          </div>
        </div>
        <div className="body stack-md">
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

          {submitError !== null && <p className="field-error">{submitError}</p>}

          <Button type="submit" disabled={isSubmitting} className="w-full">
            {isSubmitting ? t('auth.login.logging_in') : t('auth.login.submit')}
          </Button>
        </div>
      </form>
    </div>
  );
}
