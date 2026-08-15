// Shared base utilities for the status badges, regenerated from the retired
// `.badge` component class (C5 W3 波(a), #369).
//
// Five call sites carried the same rule, so the utility string lives here rather
// than being repeated. Tone stays at the use site: it is runtime-driven
// (`data-tone`) on three of them and a fixed pair on the other two.
//
// The dot is the old `.badge::before`. The `before:` variant already emits
// `content: var(--tw-content)`, whose `@property` initial value is `""` — the
// same declaration the old rule spelled out. `before:rounded-full` resolves to
// `--radius-full` (999px); on a 6×6 box CSS scales the radii down to 50% of the
// box, i.e. the identical circle the old `border-radius: 50%` drew.
//
// `text-2xs` is a font-size-only token (no `--text-2xs--line-height` partner), so
// it cannot smuggle in a line-height — but the old rule set `line-height: 1.4`
// explicitly, so `leading-badge` carries it (判例40).
export const BADGE_BASE =
  'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.75 text-2xs font-semibold ' +
  'leading-badge before:w-1.5 before:h-1.5 before:rounded-full before:bg-current';
