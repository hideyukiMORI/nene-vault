import { forwardRef, type InputHTMLAttributes, type ReactNode } from 'react';

import { CHOICE_LABEL } from './fieldBase';

export interface CheckboxProps extends InputHTMLAttributes<HTMLInputElement> {
  /** Text shown next to the box. */
  label: ReactNode;
}

// forwardRef so React Hook Form's register() can attach its ref.
export const Checkbox = forwardRef<HTMLInputElement, CheckboxProps>(function Checkbox(
  { label, className, ...rest },
  ref,
) {
  return (
    // Regenerated from `.checkbox` (#361); the box itself was `.checkbox input`.
    <label className={CHOICE_LABEL}>
      <input ref={ref} type="checkbox" className={className} {...rest} />
      <span>{label}</span>
    </label>
  );
});
