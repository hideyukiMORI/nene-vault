import { forwardRef, type InputHTMLAttributes } from 'react';

export type InputProps = InputHTMLAttributes<HTMLInputElement>;

// forwardRef so React Hook Form's register() can attach its ref and read the
// field value. Without this the input is uncontrolled-but-unregistered and the
// form submits empty values.
export const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
  { className, ...rest },
  ref,
) {
  return (
    <input
      ref={ref}
      className={`rounded-md border border-border bg-surface-raised px-inline-md py-stack-sm font-sans text-body text-text-primary ${className ?? ''}`}
      {...rest}
    />
  );
});
