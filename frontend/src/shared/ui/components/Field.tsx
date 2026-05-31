import type { ReactNode } from 'react';
import { Text } from '@/shared/ui/primitives/Text';
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
  /** Use the muted label tone (filters) instead of the default medium weight. */
  labelTone?: 'default' | 'muted';
}

/**
 * Labelled form field: label (+ optional required marker), control, and optional
 * hint / error. Replaces the repeated `flex flex-col gap-stack-xs` + label blocks
 * across every form.
 */
export function Field({
  label,
  children,
  required = false,
  hint,
  error,
  labelTone = 'default',
}: FieldProps) {
  const { t } = useTranslation();
  const labelClass =
    labelTone === 'muted' ? 'text-label-sm text-muted' : 'text-label-sm font-medium';

  return (
    <div className="flex flex-col gap-stack-xs">
      <label className={labelClass}>
        {label}
        {required && (
          <span className="ml-1 text-danger text-label-xs">{t('common.required_marker')}</span>
        )}
      </label>
      {children}
      {hint !== undefined && hint !== null && (
        <Text tone="muted" className="text-label-xs">
          {hint}
        </Text>
      )}
      {error !== undefined && error !== null && (
        <Text tone="danger" className="text-label-xs">
          {error}
        </Text>
      )}
    </div>
  );
}
