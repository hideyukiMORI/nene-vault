// What this product adds on top of the kit's `Badge` (0.17.0) through `className`:
// the type the kit has no slot for (2xs, semibold, the explicit 1.4 line-height — 判例40)
// and the status dot in front of the label, which no other ship draws (fleet reply
// 2026-08-25). Colours, radius and padding are slot values in `themes/default.css`.
//
// `before:` already emits `content: var(--tw-content)` (initial `""`); `before:rounded-full`
// on a 6×6 box is the same circle the retired `.badge::before { border-radius: 50% }` drew.
export const BADGE_CHROME =
  'text-2xs font-semibold leading-badge gap-1.5 ' +
  'before:w-1.5 before:h-1.5 before:rounded-full before:bg-current';
