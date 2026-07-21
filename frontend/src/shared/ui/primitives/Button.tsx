import type { ButtonHTMLAttributes, ReactNode } from 'react';

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost';
  size?: 'md' | 'sm';
  children: ReactNode;
}

// Regenerated from the `.btn`/`.btn-*` component classes (C5 W3 波B). The variant
// is resolved inside the component (prop → map lookup), so the FC-1 form is a
// VARIANT_UTILS object-map of utility strings (判例#35) — not a `data-variant`
// attribute, which is reserved for use-site runtime-driven variants (badge type).
// hover: lives inside each map value. `font: inherit`, transparent background and
// `border-radius: 0` are dropped: Tailwind v4 preflight resets `button` to those
// already. `.btn svg` → `[&_svg]:*`; `[disabled]` → `disabled:`; the active nudge
// is gated to `enabled:` so disabled buttons don't translate (was
// `:where(.btn)[disabled]:active { transform: none }`).
const BASE =
  'btn-transition font-semibold inline-flex items-center justify-center gap-1.75 rounded-sm border border-transparent cursor-pointer whitespace-nowrap enabled:active:translate-y-px disabled:opacity-45 disabled:cursor-not-allowed disabled:shadow-none [&_svg]:w-3.75 [&_svg]:h-3.75 [&_svg]:stroke-current';

const VARIANT_UTILS: Record<NonNullable<ButtonProps['variant']>, string> = {
  primary: 'bg-accent text-on-accent shadow-sm hover:bg-accent-hover',
  secondary:
    'bg-surface-raised text-x-ink-deep border-border-strong hover:bg-surface-sunken hover:border-text-faint',
  ghost: 'bg-transparent text-text-muted hover:bg-surface-sunken hover:text-x-ink-deep',
  danger: 'bg-danger text-on-accent hover:bg-x-danger-hover',
};

const SIZE_UTILS: Record<NonNullable<ButtonProps['size']>, string> = {
  md: 'px-3.75 py-2 text-sm max-md:min-h-11.5 max-md:px-4 max-md:py-2.75',
  sm: 'px-2.75 py-1.25 text-xs max-md:min-h-9.5 max-md:px-3 max-md:py-1.75',
};

export function Button({
  variant = 'primary',
  size = 'md',
  className,
  children,
  ...rest
}: ButtonProps) {
  return (
    <button
      className={`${BASE} ${VARIANT_UTILS[variant]} ${SIZE_UTILS[size]} ${className ?? ''}`}
      {...rest}
    >
      {children}
    </button>
  );
}
