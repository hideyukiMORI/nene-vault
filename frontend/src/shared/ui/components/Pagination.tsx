import { Button } from '@/shared/ui/primitives/Button';

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

  return (
    <div className="pagination">
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
