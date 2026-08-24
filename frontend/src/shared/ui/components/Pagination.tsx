import { Button } from '@hideyukimori/nene2-ui';

interface PaginationProps {
  total: number;
  canPrev: boolean;
  canNext: boolean;
  onPrev: () => void;
  onNext: () => void;
  /**
   * Resolved "showing {from}–{to} of {total}" range text, formatted by the
   * consumer (this component holds no i18n — fleet 会議R1②).
   */
  showingLabel: string;
  /** Resolved label for the previous-page button. */
  previousLabel: string;
  /** Resolved label for the next-page button. */
  nextLabel: string;
}

/**
 * The page-level pagination row: a rule separating it from the table above, the range on the
 * left and the controls on the right.
 *
 * 🔴 Not `@hideyukimori/nene2-ui`'s `Pagination`, which is a bare `<nav>` holding the two
 * buttons and the status between them, with no row chrome and no `className` (#390 wave 3).
 * Adopting it would move the range from the left to the middle and drop the rule and the
 * padding on three screens. Same name, different component. The buttons inside *are* the
 * kit's — which is the part that was duplicated.
 */
export function Pagination({
  total,
  canPrev,
  canNext,
  onPrev,
  onNext,
  showingLabel,
  previousLabel,
  nextLabel,
}: PaginationProps) {
  if (total === 0) {
    return null;
  }

  // 🔴 `leading-inherit` next to `text-xs`, and it is not decoration (#410).
  //
  // The retired `.pagination` rule set a font-size and no line-height, so everything inside
  // inherited the body's 1.55. Draining it to `text-xs` on 2026-07-22 (08123b4) brought
  // Tailwind's companion `--text-xs--line-height` (1.333) along, and the `sm` buttons in here
  // inherited that instead — 18.6px became 16px and the buttons lost 4.6px of height. It has
  // been in `main` since July; nothing compared against a rendered page until #404.
  //
  // 🔑 The `sm` size was a red herring. `sm` buttons exist nowhere else in this product, so
  // "only sm is wrong" was really "only this container is wrong" (measured 2026-08-24).
  //
  // ⚠️ 判例40's neutraliser did not exist yet when this was drained — `leading-inherit` was
  // added on 08-13, three weeks later. This is a regression nothing could have caught at the
  // time, corrected now rather than a design value being changed back (owner ruling 08-24).
  return (
    <div className="flex items-center justify-between px-4 py-3 border-t border-border text-xs leading-inherit text-text-muted max-md:flex-col max-md:gap-3 max-md:items-stretch max-md:text-center">
      <span>{showingLabel}</span>
      <div className="flex items-center gap-2 max-md:justify-center">
        {/* max-md:flex-1 preserves the retired `.pagination .btn { flex: 1 }`
            mobile rule now that `.btn` is utility-based (C5 W3 波B). */}
        <Button
          variant="secondary"
          size="sm"
          className="max-md:flex-1"
          onClick={onPrev}
          disabled={!canPrev}
        >
          {previousLabel}
        </Button>
        <Button
          variant="secondary"
          size="sm"
          className="max-md:flex-1"
          onClick={onNext}
          disabled={!canNext}
        >
          {nextLabel}
        </Button>
      </div>
    </div>
  );
}
