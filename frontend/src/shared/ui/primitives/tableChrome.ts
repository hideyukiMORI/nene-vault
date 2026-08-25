// This product's table look on top of the kit's `DataTable` (0.17.0), applied through
// `className` (owner NG on the kit defaults, 2026-08-26). What a slot can express — header
// weight, cell padding, border colour — is a slot value in `themes/default.css`; what it
// cannot — the header's size / case / tracking / ground, the hover, the last row's border —
// is here, scoped with arbitrary variants so no component class comes back (規約⑤).

/** Desktop chrome: the retired `table.tbl` / `thead th` rules, expressed as variants. */
export const TABLE_CHROME =
  'text-sm ' +
  '[&_thead_th]:bg-surface-sunken [&_thead_th]:text-2xs [&_thead_th]:uppercase ' +
  '[&_thead_th]:tracking-meta [&_thead_th]:whitespace-nowrap [&_thead_th]:border-x-line-mid ' +
  '[&_tbody_tr:last-child_td]:border-b-0 [&_tbody_tr:hover]:bg-surface-overlay';

/**
 * Card chrome below `sm`, on top of the kit's `collapse="sm"` (which provides the structure:
 * block cells, `sr-only` header, `data-label` in `::before`). The retired `.tbl-cards` drew
 * each cell as one row — label on the left in a fixed column, value on the right.
 */
export const TABLE_CARDS =
  'max-sm:[&_tbody_tr]:px-4 max-sm:[&_tbody_tr]:py-3.5 max-sm:[&_tbody_tr:hover]:bg-transparent ' +
  'max-sm:[&_tbody_td]:flex max-sm:[&_tbody_td]:items-baseline max-sm:[&_tbody_td]:gap-3 ' +
  'max-sm:[&_tbody_td]:px-0 max-sm:[&_tbody_td]:py-1 max-sm:[&_tbody_td]:border-0 ' +
  'max-sm:[&_tbody_td::before]:inline-block max-sm:[&_tbody_td::before]:w-26 max-sm:[&_tbody_td::before]:flex-none ' +
  'max-sm:[&_tbody_td::before]:text-2xs max-sm:[&_tbody_td::before]:font-semibold max-sm:[&_tbody_td::before]:uppercase ' +
  'max-sm:[&_tbody_td::before]:tracking-meta max-sm:[&_tbody_td::before]:text-text-muted';

/**
 * The card's title cell (the retired `.cell-title`): full width, body size, bold, a rule
 * beneath it, no label. One literal per column index — Tailwind's scanner only sees class
 * names that exist verbatim in the source, so these cannot be built from a template.
 */
export const TABLE_CARD_TITLE_COL1 =
  'max-sm:[&_tbody_td:nth-child(1)]:block max-sm:[&_tbody_td:nth-child(1)]:text-body ' +
  'max-sm:[&_tbody_td:nth-child(1)]:font-semibold max-sm:[&_tbody_td:nth-child(1)]:text-x-ink-deep ' +
  'max-sm:[&_tbody_td:nth-child(1)]:pb-2 max-sm:[&_tbody_td:nth-child(1)]:mb-1.5 ' +
  'max-sm:[&_tbody_td:nth-child(1)]:border-b max-sm:[&_tbody_td:nth-child(1)]:border-border ' +
  'max-sm:[&_tbody_td:nth-child(1)::before]:hidden';
export const TABLE_CARD_TITLE_COL2 =
  'max-sm:[&_tbody_td:nth-child(2)]:block max-sm:[&_tbody_td:nth-child(2)]:text-body ' +
  'max-sm:[&_tbody_td:nth-child(2)]:font-semibold max-sm:[&_tbody_td:nth-child(2)]:text-x-ink-deep ' +
  'max-sm:[&_tbody_td:nth-child(2)]:pb-2 max-sm:[&_tbody_td:nth-child(2)]:mb-1.5 ' +
  'max-sm:[&_tbody_td:nth-child(2)]:border-b max-sm:[&_tbody_td:nth-child(2)]:border-border ' +
  'max-sm:[&_tbody_td:nth-child(2)::before]:hidden';
