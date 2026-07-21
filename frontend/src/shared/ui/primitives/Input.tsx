import { forwardRef, type InputHTMLAttributes } from 'react';

export type InputProps = InputHTMLAttributes<HTMLInputElement>;

// forwardRef so React Hook Form's register() can attach its ref and read the
// field value. Without this the input is uncontrolled-but-unregistered and the
// form submits empty values.
export const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
  { className, ...rest },
  ref,
) {
  // Invalid state (regenerated from the retired `.input-warn` class, C5 W3 波W3):
  // the styling hangs off the `aria-invalid` attribute so accessibility and paint
  // come from the same source (FC-1.8). Callers just set `aria-invalid`.
  return (
    <input
      ref={ref}
      className={`input aria-invalid:border-warn aria-invalid:ring-3 aria-invalid:ring-warn-soft ${className ?? ''}`}
      {...rest}
    />
  );
});
