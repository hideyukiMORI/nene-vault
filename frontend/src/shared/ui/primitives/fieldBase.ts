// Shared base utility for the choice controls.
//
// 🔴 `FIELD_BASE` / `FIELD_PLACEHOLDER` lived here until #387. They were the regenerated
// `.input`/`.select`/`.textarea` rules from the C5 W3 drain (#361), and they went with the
// components they served when those moved to `@hideyukimori/nene2-ui`. The one part that did
// not travel — the `max-md:` font-size bump that keeps iOS Safari from zooming on focus —
// is now a base-layer rule in `theme/base.css`, because the kit's controls set no font-size.

// `.checkbox` and `.radio` were one shared rule (plus `.checkbox input`/`.radio
// input` for the control itself), so the label wrapper keeps a single utility
// string here rather than drifting between Checkbox and the export-format radios.
export const CHOICE_LABEL =
  'inline-flex items-center gap-2.25 cursor-pointer text-sm leading-inherit text-text-primary ' +
  '[&_input]:w-4 [&_input]:h-4 [&_input]:accent-accent [&_input]:flex-none';
