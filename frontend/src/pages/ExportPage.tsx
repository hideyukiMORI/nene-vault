import {
  Button,
  Card,
  Checkbox,
  FormField,
  Grid,
  Input,
  Radio,
  Stack,
} from '@hideyukimori/nene2-ui';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { authStore } from '@/shared/api/auth-session';
import { useExportDocuments } from '@/entities/document';
import { useTranslation } from '@/shared/i18n/use-translation';
import { AppChrome } from '@/features/app-chrome';

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
    setExportError(null);
    setExportSuccess(false);
    // #452: a reversed range used to go straight to the API and come back as an empty manifest —
    // "downloaded", nothing in it, no hint why. ISO dates compare as strings.
    if (dateFrom !== '' && dateTo !== '' && dateFrom > dateTo) {
      setExportError(t('export.errors.date_range'));
      return;
    }
    setIsExporting(true);

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
      <Stack gap="2xs">
        <span className="text-2xs tracking-eyebrow uppercase text-x-brass-deep font-semibold">
          {t('navigation.export')}
        </span>
        <h1 className="text-h1 font-semibold tracking-title text-x-ink-deep">
          {t('export.title')}
        </h1>
        <p className="text-text-muted text-sm max-w-lede">{t('export.description')}</p>
      </Stack>

      <Card raised pad="md">
        <Stack gap="sm">
          <Grid cols={{ base: 1, sm: 2 }} gap="sm">
            <FormField id="export-date-from" label={t('export.form.date_from_label')}>
              <Input
                type="date"
                value={dateFrom}
                onChange={(e) => {
                  setDateFrom(e.target.value);
                }}
              />
            </FormField>
            <FormField id="export-date-to" label={t('export.form.date_to_label')}>
              <Input
                type="date"
                value={dateTo}
                onChange={(e) => {
                  setDateTo(e.target.value);
                }}
              />
            </FormField>
          </Grid>

          <FormField id="export-counterparty" label={t('export.form.counterparty_label')}>
            <Input
              type="text"
              value={counterparty}
              onChange={(e) => {
                setCounterparty(e.target.value);
              }}
            />
          </FormField>

          <FormField id="export-format" label={t('export.form.format_label')}>
            {/* `align="start"` so the labels keep their own width. A Stack blockifies its
                children, and a choice label that spans the full row moves the click target
                somewhere the eye does not expect it. */}
            <Stack align="start" gap="2xs">
              {(['zip', 'csv'] as const).map((f) => (
                <Radio
                  key={f}
                  name="export-format"
                  value={f}
                  checked={format === f}
                  onChange={() => {
                    setFormat(f);
                  }}
                  label={t(f === 'zip' ? 'export.form.format_zip' : 'export.form.format_csv')}
                />
              ))}
            </Stack>
          </FormField>

          {/* `self-start` so the label keeps its own width — as a flex item it would
              otherwise span the whole card and put the click target where nothing is drawn.
              Said directly on the control since 0.11.0: `className` lands on the `<label>`
              now, and the box takes `inputClassName`. Until then this needed a wrapper. */}
          <Checkbox
            className="self-start"
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
              onClick={() => {
                void handleExport();
              }}
              disabled={isExporting}
            >
              {isExporting ? t('common.status.processing') : t('export.form.submit')}
            </Button>
          </div>
        </Stack>
      </Card>
    </AppChrome>
  );
}
