import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { authStore } from '@/shared/api/auth-session';
import { useExportDocuments } from '@/entities/document';
import { useTranslation } from '@/shared/i18n/use-translation';
import { AppChrome } from '@/features/app-chrome';
import { Button } from '@/shared/ui/primitives/Button';
import { Checkbox } from '@/shared/ui/primitives/Checkbox';
import { Field } from '@/shared/ui/components/Field';
import { Input } from '@/shared/ui/primitives/Input';

export function ExportPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const session = authStore.getSession();
  const exportMutation = useExportDocuments();
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
    void navigate('/login', { replace: true });
  }

  async function handleExport() {
    setIsExporting(true);
    setExportError(null);
    setExportSuccess(false);

    try {
      // Go through the shared API client (via the entity hook) so the request
      // carries the X-Authorization mirror (#118); a raw fetch drops it and the
      // export 401s behind the shared-hosting proxy that strips Authorization.
      const { blob, filename } = await exportMutation.mutateAsync({
        format,
        include_voided: includeVoided,
        transaction_date_from: dateFrom,
        transaction_date_to: dateTo,
        counterparty_name: counterparty,
      });

      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename ?? (format === 'csv' ? 'export.csv' : 'export.zip');
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
    <AppChrome
      onLogout={handleLogout}
      userEmail={session?.email}
      userRole={session?.role}
      width="narrow"
    >
      <div className="flex flex-col gap-1.5">
        <span className="text-2xs tracking-eyebrow uppercase text-x-brass-deep font-semibold">
          {t('navigation.export')}
        </span>
        <h1 className="text-h1 font-semibold tracking-title text-x-ink-deep">
          {t('export.title')}
        </h1>
        <p className="text-text-muted text-sm max-w-lede">{t('export.description')}</p>
      </div>

      <div className="card p-4.5 space-y-4">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
          <div className="space-y-1.5">
            {(['zip', 'csv'] as const).map((f) => (
              <label key={f} className="radio">
                <input
                  type="radio"
                  name="export-format"
                  value={f}
                  checked={format === f}
                  onChange={() => {
                    setFormat(f);
                  }}
                />
                <span>{t(f === 'zip' ? 'export.form.format_zip' : 'export.form.format_csv')}</span>
              </label>
            ))}
          </div>
        </Field>

        <Checkbox
          label={t('export.form.include_voided_label')}
          checked={includeVoided}
          onChange={(e) => {
            setIncludeVoided(e.target.checked);
          }}
        />

        {exportError !== null && <p className="text-2xs text-danger">{exportError}</p>}
        {exportSuccess && <p className="success body-sm">{t('export.messages.downloaded')}</p>}

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
      </div>
    </AppChrome>
  );
}
