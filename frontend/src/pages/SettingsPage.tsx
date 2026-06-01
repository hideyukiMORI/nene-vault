import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { authStore } from '@/entities/auth';
import { useVaultSettings, useUpdateVaultSettings } from '@/entities/vault-settings';
import { messageKeyForError } from '@/shared/i18n/map-problem-details';
import { useTranslation } from '@/shared/i18n/use-translation';
import { formatDateTime } from '@/shared/lib/format';
import { AppShell, Button, Field, Input } from '@/shared/ui';
import { useNavigate } from 'react-router-dom';

const settingsSchema = z.object({
  retention_years: z.coerce.number().int().min(7).max(99),
  storage_path_override: z.string().optional(),
  invoice_api_base_url: z.string().optional(),
  clear_api_base_url: z.string().optional(),
});

type SettingsFormValues = z.infer<typeof settingsSchema>;

export function SettingsPage() {
  const { t, locale } = useTranslation();
  const navigate = useNavigate();
  const session = authStore.getSession();
  const { data: settings, isLoading } = useVaultSettings();

  const form = useForm<SettingsFormValues>({
    resolver: zodResolver(settingsSchema),
    defaultValues: {
      retention_years: 10,
      storage_path_override: '',
      invoice_api_base_url: '',
      clear_api_base_url: '',
    },
  });

  const {
    register,
    watch,
    reset,
    formState: { errors },
  } = form;
  const retentionYears = watch('retention_years');
  const retentionWarn = typeof retentionYears === 'number' && retentionYears < 10;

  useEffect(() => {
    if (settings !== undefined) {
      reset({
        retention_years: settings.retention_years,
        storage_path_override: settings.storage_path_override ?? '',
        invoice_api_base_url: settings.invoice_api_base_url ?? '',
        clear_api_base_url: settings.clear_api_base_url ?? '',
      });
    }
  }, [settings, reset]);

  const mutation = useUpdateVaultSettings();
  const submitError =
    mutation.error !== null
      ? (messageKeyForError(mutation.error) ?? 'problem.internal_server_error')
      : null;

  function handleLogout() {
    authStore.clearSession();
    navigate('/login', { replace: true });
  }

  return (
    <AppShell
      onLogout={handleLogout}
      userEmail={session?.email}
      userRole={session?.role}
      width="narrow"
    >
      <div className="titlebar">
        <span className="eyebrow">{t('navigation.settings')}</span>
        <h1 className="page-title">{t('vault_settings.title')}</h1>
      </div>

      {isLoading ? (
        <div className="empty-state">{t('common.status.loading')}</div>
      ) : (
        <form
          className="card p-md stack-md"
          onSubmit={(e) => {
            void form.handleSubmit((values) => {
              mutation.mutate({
                retention_years: values.retention_years,
                storage_path_override:
                  values.storage_path_override !== '' ? values.storage_path_override : null,
                invoice_api_base_url:
                  values.invoice_api_base_url !== '' ? values.invoice_api_base_url : null,
                clear_api_base_url:
                  values.clear_api_base_url !== '' ? values.clear_api_base_url : null,
              });
            })(e);
          }}
        >
          <Field
            label={t('vault_settings.fields.retention_years_label')}
            hint={t('vault_settings.fields.retention_years_hint')}
            error={errors.retention_years !== undefined ? t('common.required_marker') : undefined}
          >
            <Input
              type="number"
              min={7}
              max={99}
              className={retentionWarn ? 'input-warn' : ''}
              {...register('retention_years')}
            />
            {retentionWarn && (
              <div className="callout callout-warn">
                {t('vault_settings.fields.retention_warning')}
              </div>
            )}
          </Field>

          <Field
            label={t('vault_settings.fields.storage_path_label')}
            hint={t('vault_settings.fields.storage_path_hint')}
          >
            <Input
              type="text"
              placeholder={t('vault_settings.fields.storage_path_placeholder')}
              {...register('storage_path_override')}
            />
          </Field>

          <Field label={t('vault_settings.fields.invoice_api_base_url_label')}>
            <Input
              type="url"
              placeholder={t('vault_settings.fields.invoice_api_base_url_placeholder')}
              {...register('invoice_api_base_url')}
            />
          </Field>

          <Field label={t('vault_settings.fields.clear_api_base_url_label')}>
            <Input
              type="url"
              placeholder={t('vault_settings.fields.clear_api_base_url_placeholder')}
              {...register('clear_api_base_url')}
            />
          </Field>

          {settings?.updated_at !== null && settings?.updated_at !== undefined && (
            <p className="muted label-xs">
              {t('vault_settings.fields.updated_at_label')}:{' '}
              {formatDateTime(settings.updated_at, locale)}
            </p>
          )}

          {mutation.isSuccess && (
            <p className="success body-sm">{t('vault_settings.messages.saved')}</p>
          )}
          {submitError !== null && <p className="field-error">{t(submitError)}</p>}

          <div>
            <Button type="submit" variant="primary" disabled={mutation.isPending}>
              {mutation.isPending ? t('common.status.saving') : t('vault_settings.save_button')}
            </Button>
          </div>
        </form>
      )}
    </AppShell>
  );
}
