import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { authStore } from '@/entities/auth';
import { useVaultSettings, useUpdateVaultSettings } from '@/entities/vault-settings';
import { messageKeyForError } from '@/shared/i18n/map-problem-details';
import { useTranslation } from '@/shared/i18n/use-translation';
import { formatDateTime } from '@/shared/lib/format';
import { AppShell, Button, Field, Input, Stack, Text } from '@/shared/ui';
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
    <AppShell onLogout={handleLogout}>
      <div className="max-w-2xl">
        <Stack gap="lg">
          <Text as="h1" className="text-heading-md">
            {t('vault_settings.title')}
          </Text>

          {isLoading ? (
            <Text tone="muted">{t('common.status.loading')}</Text>
          ) : (
            <form
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
              <Stack gap="md">
                <Field
                  label={t('vault_settings.fields.retention_years_label')}
                  hint={t('vault_settings.fields.retention_years_hint')}
                  error={
                    errors.retention_years !== undefined ? t('common.required_marker') : undefined
                  }
                >
                  <Input type="number" min={7} max={99} {...register('retention_years')} />
                  {typeof retentionYears === 'number' && retentionYears < 10 && (
                    <div className="rounded-md border border-warning bg-warning-muted p-stack-sm">
                      <Text className="text-label-sm text-warning">
                        {t('vault_settings.fields.retention_warning')}
                      </Text>
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
                  <Text tone="muted" className="text-label-xs">
                    {t('vault_settings.fields.updated_at_label')}:{' '}
                    {formatDateTime(settings.updated_at, locale)}
                  </Text>
                )}

                {mutation.isSuccess && (
                  <Text tone="success" className="text-body-sm">
                    {t('vault_settings.messages.saved')}
                  </Text>
                )}

                {submitError !== null && (
                  <Text tone="danger" className="text-body-sm">
                    {t(submitError)}
                  </Text>
                )}

                <div>
                  <Button type="submit" variant="primary" disabled={mutation.isPending}>
                    {mutation.isPending
                      ? t('common.status.saving')
                      : t('vault_settings.save_button')}
                  </Button>
                </div>
              </Stack>
            </form>
          )}
        </Stack>
      </div>
    </AppShell>
  );
}
