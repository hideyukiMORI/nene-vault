// The status dot in front of a badge label — this product's own decoration, layered on the
// kit's `Badge` through its `className` (0.17.0, fleet #390). The kit measured five ships and
// none of the others draw a dot, so it stays here rather than upstream (fleet reply 2026-08-25).
//
// `before:` already emits `content: var(--tw-content)` (initial `""`), so no explicit content
// is needed. `before:rounded-full` on a 6×6 box scales down to the same circle the retired
// `.badge::before { border-radius: 50% }` drew. `gap-1.5` is the space between dot and label.
export const BADGE_DOT = 'gap-1.5 before:w-1.5 before:h-1.5 before:rounded-full before:bg-current';
