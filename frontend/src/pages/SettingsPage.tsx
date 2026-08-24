import { Button, EmptyState, FormField, InlineAlert, Input, Stack } from '@hideyukimori/nene2-ui';
import { useEffect } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { VALIDATION, fieldErrorText } from '@/shared/i18n/validation-keys';
import { authStore } from '@/shared/api/auth-session';
import { useVaultSettings, useUpdateVaultSettings } from '@/entities/vault-settings';
import { messageKeyForError } from '@/shared/i18n/map-problem-details';
import { useTranslation } from '@/shared/i18n/use-translation';
import { formatDateTime } from '@/shared/lib/format';
import { AppChrome } from '@/features/app-chrome';
import { useNavigate } from 'react-router-dom';

/** Referenced by the retention input's `aria-describedby`, so it lives next to both. */
const RETENTION_WARNING_ID = 'settings-retention-warning';

const settingsSchema = z.object({
  retention_years: z.coerce
    .number()
    .int(VALIDATION.invalidFormat)
    .min(7, VALIDATION.tooSmall)
    .max(99, VALIDATION.tooLarge),
  storage_path_override: z.string().optional(),
  invoice_api_base_url: z.string().optional(),
  clear_api_base_url: z.string().optional(),
});

type SettingsFormValues = z.infer<typeof settingsSchema>;

// Blank or absent optional text clears the setting. The API has no "leave
// unchanged" sentinel — the handler folds absent/null/'' into null alike.
function toNullable(value: string | undefined): string | null {
  return value === undefined || value === '' ? null : value;
}

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
    reset,
    control,
    formState: { errors },
  } = form;
  // useWatch (not watch()): memoization-safe under the React Compiler /
  // react-hooks v7 lint (watch() cannot be memoized without stale UI).
  const retentionYears = useWatch({ control, name: 'retention_years' });
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
    void navigate('/login', { replace: true });
  }

  return (
    <AppChrome
      onLogout={handleLogout}
      userEmail={session?.email}
      userRole={session?.role}
      width="narrow"
    >
      <Stack gap="2xs">
        <span className="text-2xs tracking-eyebrow uppercase text-x-brass-deep font-semibold">
          {t('navigation.settings')}
        </span>
        <h1 className="text-h1 font-semibold tracking-title text-x-ink-deep">
          {t('vault_settings.title')}
        </h1>
      </Stack>

      {isLoading ? (
        <EmptyState message={t('common.status.loading')} />
      ) : (
        <form
          /* `<form>`, so not the kit's Card (which is a `<div>`) — the retired `.card`
             rule is spelled out here instead (#417). */
          className="bg-surface-raised border border-border rounded-md shadow-sm p-x-md"
          onSubmit={(e) => {
            void form.handleSubmit((values) => {
              mutation.mutate({
                retention_years: values.retention_years,
                storage_path_override: toNullable(values.storage_path_override),
                invoice_api_base_url: toNullable(values.invoice_api_base_url),
                clear_api_base_url: toNullable(values.clear_api_base_url),
              });
            })(e);
          }}
        >
          <Stack gap="sm">
            <FormField
              id="settings-retention-years"
              label={t('vault_settings.fields.retention_years_label')}
              hint={t('vault_settings.fields.retention_years_hint')}
              error={fieldErrorText(t, errors.retention_years)}
            >
              <Input
                type="number"
                min={7}
                max={99}
                /* 🔴 No `aria-invalid` here. Eight years is under the recommended ten and over
                   the `min` of seven — the value passes validation, and `aria-invalid` says it
                   did not. It used to be set, which told assistive technology the field was
                   wrong while the reason went unlinked: the exact shape #385 was opened for,
                   surviving in the one place that is not an error at all.

                   The notice is linked instead. Only when there is no real validation error —
                   an explicit `aria-describedby` wins over FormField's own wiring, and an
                   error outranks a recommendation. */
                aria-describedby={
                  retentionWarn && errors.retention_years === undefined
                    ? RETENTION_WARNING_ID
                    : undefined
                }
                /* 🔴 The amber paint, back where it belongs. Before the migration it came
                   from vault's own Input (`aria-invalid:border-warn` and friends — attribute
                   and paint from one source, FC-1.8); W1 moved the Input upstream and the
                   paint did not come along, which nothing noticed because the test asserted
                   the attribute and the attribute was still there.

                   0.12.0 gave the kit a warning state that is deliberately NOT `aria-invalid`
                   (#399): `data-warn` carries no ARIA meaning, so a legal value is painted
                   without being announced as an error. Presence is what the selector matches,
                   so the value is the empty string — `data-warn={false}` would still render
                   the attribute. The two assertions in
                   `SettingsPage.test.tsx` are what hold this — the mark and the paint, separately. */
                data-warn={retentionWarn ? '' : undefined}
                // valueAsNumber so the watched value is numeric *while typing* — the
                // under-10-years compliance warning must render live, not only after
                // save → re-fetch coerces the value server-side.
                {...register('retention_years', { valueAsNumber: true })}
              />
              {retentionWarn && (
                /* The id goes on the alert itself since 0.12.0 (#399); before that the kit's
                   InlineAlert took neither `id` nor `className` and this needed a wrapper. */
                <InlineAlert id={RETENTION_WARNING_ID} tone="warn">
                  {t('vault_settings.fields.retention_warning')}
                </InlineAlert>
              )}
            </FormField>

            <FormField
              id="settings-storage-path"
              label={t('vault_settings.fields.storage_path_label')}
              hint={t('vault_settings.fields.storage_path_hint')}
            >
              <Input
                type="text"
                placeholder={t('vault_settings.fields.storage_path_placeholder')}
                {...register('storage_path_override')}
              />
            </FormField>

            <FormField
              id="settings-invoice-api-base-url"
              label={t('vault_settings.fields.invoice_api_base_url_label')}
            >
              <Input
                type="url"
                placeholder={t('vault_settings.fields.invoice_api_base_url_placeholder')}
                {...register('invoice_api_base_url')}
              />
            </FormField>

            <FormField
              id="settings-clear-api-base-url"
              label={t('vault_settings.fields.clear_api_base_url_label')}
            >
              <Input
                type="url"
                placeholder={t('vault_settings.fields.clear_api_base_url_placeholder')}
                {...register('clear_api_base_url')}
              />
            </FormField>

            {settings?.updated_at !== null && settings?.updated_at !== undefined && (
              <p className="text-text-muted label-xs">
                {t('vault_settings.fields.updated_at_label')}:{' '}
                {formatDateTime(settings.updated_at, locale)}
              </p>
            )}

            {mutation.isSuccess && (
              <p className="success body-sm">{t('vault_settings.messages.saved')}</p>
            )}
            {submitError !== null && <p className="text-2xs text-danger">{t(submitError)}</p>}

            <div>
              <Button type="submit" variant="primary" disabled={mutation.isPending}>
                {mutation.isPending ? t('common.status.saving') : t('vault_settings.save_button')}
              </Button>
            </div>
          </Stack>
        </form>
      )}
    </AppChrome>
  );
}
