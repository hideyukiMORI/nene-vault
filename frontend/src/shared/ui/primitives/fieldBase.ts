// Shared base utilities for the text-entry form controls (Input / Select /
// Textarea), regenerated from the retired `.input`/`.select`/`.textarea`
// component classes (C5 W3 波(a), #361).
//
// The three controls carried one shared rule in `default.components.css`, so the
// utility string is shared here rather than triplicated. `font: inherit` from the
// old rule is intentionally absent: Tailwind v4's preflight already declares it
// for form controls — the same drop that the `.btn` drain measured and recorded
// (#340).
//
// The `max-md:` trio reproduces the old `@media (max-width: 767px)` touch block
// (padding 11px/12px, font-size 16px). `text-touch` is a font-size-only token, so
// it does not smuggle in a line-height the old declaration never set.
export const FIELD_BASE =
  'text-body w-full border border-x-line-mid bg-surface-raised rounded-sm py-2 px-2.75 ' +
  'text-x-ink-deep field-transition focus:outline-none focus:border-accent focus:ring-3 ' +
  'focus:ring-accent-soft max-md:py-2.75 max-md:px-3 max-md:text-touch';

// `.input::placeholder` / `.textarea::placeholder` — select has no placeholder.
export const FIELD_PLACEHOLDER = 'placeholder:text-text-faint';

// `.checkbox` and `.radio` were one shared rule (plus `.checkbox input`/`.radio
// input` for the control itself), so the label wrapper keeps a single utility
// string here rather than drifting between Checkbox and the export-format radios.
export const CHOICE_LABEL =
  'inline-flex items-center gap-2.25 cursor-pointer text-sm leading-inherit text-text-primary ' +
  '[&_input]:w-4 [&_input]:h-4 [&_input]:accent-accent [&_input]:flex-none';
