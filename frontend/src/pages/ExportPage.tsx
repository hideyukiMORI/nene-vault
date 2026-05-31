import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { authStore } from '@/entities/auth';
import { useTranslation } from '@/shared/i18n/use-translation';
import { AppShell, Button, Field, Input, Stack, Text } from '@/shared/ui';
import { env } from '@/shared/config/env';

export function ExportPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [counterparty, setCounterparty] = useState('');
  const [includeVoided, setIncludeVoided] = useState(false);
  const [format, setFormat] = useState<'zip' | 'csv'>('zip');
  const [isExporting, setIsExporting] = useState(false);
  const [exportError, setExportError] = useState<string | null>(null);
  const [exportSuccess, setExportSuccess] = useState(false);

  function handleLogout() {
    authStore.clearSession();
    navigate('/login', { replace: true });
  }

  async function handleExport() {
    setIsExporting(true);
    setExportError(null);
    setExportSuccess(false);

    try {
      const base = env.apiBaseUrl.replace(/\/$/, '');
      const body: Record<string, unknown> = { include_voided: includeVoided, format };
      if (dateFrom !== '') body['transaction_date_from'] = dateFrom;
      if (dateTo !== '') body['transaction_date_to'] = dateTo;
      if (counterparty !== '') body['counterparty_name'] = counterparty;

      const token = authStore.getToken();
      const headers: Record<string, string> = { 'Content-Type': 'application/json' };
      if (token !== null) headers['Authorization'] = `Bearer ${token}`;

      const response = await fetch(`${base}/admin/vault/export`, {
        method: 'POST',
        headers,
        credentials: 'include',
        body: JSON.stringify(body),
      });

      if (!response.ok) {
        setExportError(t('common.status.error'));
        return;
      }

      const blob = await response.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      const cd = response.headers.get('Content-Disposition') ?? '';
      const match = /filename="?([^"]+)"?/.exec(cd);
      a.download = match?.[1] ?? 'export.zip';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
      setExportSuccess(true);
    } catch {
      setExportError(t('common.status.error'));
    } finally {
      setIsExporting(false);
    }
  }

  return (
    <AppShell onLogout={handleLogout}>
      <div className="max-w-2xl">
        <Stack gap="lg">
          <Text as="h1" className="text-heading-md">
            {t('export.title')}
          </Text>

          <Text tone="muted" className="text-body-sm">
            {t('export.description')}
          </Text>

          <div className="rounded-lg border border-border bg-surface p-stack-md">
            <Stack gap="md">
              <div className="grid grid-cols-2 gap-inline-md">
                <Field label={t('export.form.date_from_label')}>
                  <Input
                    type="date"
                    value={dateFrom}
                    onChange={(e) => {
                      setDateFrom(e.target.value);
                    }}
                  />
                </Field>
                <Field label={t('export.form.date_to_label')}>
                  <Input
                    type="date"
                    value={dateTo}
                    onChange={(e) => {
                      setDateTo(e.target.value);
                    }}
                  />
                </Field>
              </div>

              <Field label={t('export.form.counterparty_label')}>
                <Input
                  type="text"
                  value={counterparty}
                  onChange={(e) => {
                    setCounterparty(e.target.value);
                  }}
                />
              </Field>

              <Field label={t('export.form.format_label')}>
                <div className="flex flex-col gap-stack-xs">
                  {(['zip', 'csv'] as const).map((f) => (
                    <label key={f} className="flex items-center gap-inline-sm cursor-pointer">
                      <input
                        type="radio"
                        name="export-format"
                        value={f}
                        checked={format === f}
                        onChange={() => {
                          setFormat(f);
                        }}
                        className="h-4 w-4 border-border text-brand focus:ring-brand"
                      />
                      <span className="text-body-sm">
                        {t(f === 'zip' ? 'export.form.format_zip' : 'export.form.format_csv')}
                      </span>
                    </label>
                  ))}
                </div>
              </Field>

              <label className="flex items-center gap-inline-sm cursor-pointer">
                <input
                  type="checkbox"
                  checked={includeVoided}
                  onChange={(e) => {
                    setIncludeVoided(e.target.checked);
                  }}
                  className="h-4 w-4 rounded border-border text-brand focus:ring-brand"
                />
                <span className="text-body-sm">{t('export.form.include_voided_label')}</span>
              </label>

              {exportError !== null && (
                <Text tone="danger" className="text-body-sm">
                  {exportError}
                </Text>
              )}
              {exportSuccess && (
                <Text tone="success" className="text-body-sm">
                  {t('export.messages.downloaded')}
                </Text>
              )}

              <div>
                <Button
                  variant="primary"
                  onClick={() => {
                    void handleExport();
                  }}
                  disabled={isExporting}
                >
                  {isExporting ? t('common.status.processing') : t('export.form.submit')}
                </Button>
              </div>
            </Stack>
          </div>
        </Stack>
      </div>
    </AppShell>
  );
}
