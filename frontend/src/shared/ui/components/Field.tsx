import type { ReactNode } from 'react';
import { useTranslation } from '@/shared/i18n/use-translation';

export interface FieldProps {
  /** Visible field label. */
  label: ReactNode;
  /** The control (Input, Select, Textarea, …). */
  children: ReactNode;
  /** Show the required marker after the label. */
  required?: boolean;
  /** Helper text shown below the control. */
  hint?: ReactNode;
  /** Error message shown below the control (locale key already resolved or a node). */
  error?: ReactNode;
}

/**
 * Labelled form field: label (+ optional required marker), control, and optional
 * hint / error — styled by the design-system `.field` block.
 */
export function Field({ label, children, required = false, hint, error }: FieldProps) {
  const { t } = useTranslation();

  return (
    <div className="field">
      <label className="field-label">
        {label}
        {required && <span className="req">{t('common.required_marker')}</span>}
      </label>
      {children}
      {hint !== undefined && hint !== null && <span className="field-hint">{hint}</span>}
      {error !== undefined && error !== null && <span className="field-error">{error}</span>}
    </div>
  );
}
