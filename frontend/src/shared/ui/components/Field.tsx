import type { ReactNode } from 'react';

export interface FieldProps {
  /** Visible field label. */
  label: ReactNode;
  /** The control (Input, Select, Textarea, …). */
  children: ReactNode;
  /** Show the required marker after the label. */
  required?: boolean;
  /**
   * Resolved required-marker content, rendered after the label when `required`
   * is set. The consumer supplies the already-translated string (or node); this
   * component holds no i18n (fleet 会議R1②).
   */
  requiredMarker?: ReactNode;
  /** Helper text shown below the control. */
  hint?: ReactNode;
  /** Error message shown below the control (locale key already resolved or a node). */
  error?: ReactNode;
}

/**
 * Labelled form field: label (+ optional required marker), control, and optional
 * hint / error — styled by the design-system `.field` block.
 */
export function Field({
  label,
  children,
  required = false,
  requiredMarker,
  hint,
  error,
}: FieldProps) {
  return (
    <div className="flex flex-col gap-1.5">
      <label className="field-label">
        {label}
        {required && requiredMarker !== undefined && requiredMarker !== null && (
          <span className="text-danger ml-1.5 text-2xs font-semibold">{requiredMarker}</span>
        )}
      </label>
      {children}
      {hint !== undefined && hint !== null && (
        <span className="text-2xs text-text-faint leading-normal">{hint}</span>
      )}
      {error !== undefined && error !== null && (
        <span className="text-2xs text-danger">{error}</span>
      )}
    </div>
  );
}
