// What this product adds on top of the kit's `Badge` (0.19.0) through `className`: the
// explicit 1.4 line-height (判例40 — a `--text-*` without its companion leaves line-height
// to whatever the cascade supplies, and the kit ships no `--text-x-slot-badge--line-height`)
// and the status dot in front of the label, which no other ship draws (fleet reply
// 2026-08-25). Colours, radius, padding, gap, size and weight are the kit's own slots.
//
// 🔴 `gap-1.5`, `text-2xs` and `font-semibold` were here until 0.19.0 gave `Badge` a gap, a
// size and a weight of its own (#481). They are not merely redundant now — they are inert:
// `className` loses to the kit's `BASE_CLASS`, same specificity, decided by the order the CSS
// is generated in, and the kit's slot-derived classes are emitted later. Keeping them would
// have left three declarations that read as if they still set something. The rendered values
// are unchanged: 4px replaces this product's 6px (deliberate, see `themes/default.css`),
// while size and weight were already identical to the kit's defaults.
//
// `before:` already emits `content: var(--tw-content)` (initial `""`); `before:rounded-full`
// on a 6×6 box is the same circle the retired `.badge::before { border-radius: 50% }` drew.
export const BADGE_CHROME =
  'leading-badge before:w-1.5 before:h-1.5 before:rounded-full before:bg-current';
