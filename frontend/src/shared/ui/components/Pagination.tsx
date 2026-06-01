import { useTranslation } from '@/shared/i18n/use-translation';
import { Button } from '@/shared/ui/primitives/Button';

interface PaginationProps {
  offset: number;
  limit: number;
  total: number;
  canPrev: boolean;
  canNext: boolean;
  onPrev: () => void;
  onNext: () => void;
}

export function Pagination({
  offset,
  limit,
  total,
  canPrev,
  canNext,
  onPrev,
  onNext,
}: PaginationProps) {
  const { t } = useTranslation();

  if (total === 0) {
    return null;
  }

  const from = offset + 1;
  const to = Math.min(offset + limit, total);

  return (
    <div className="pagination">
      <span>
        {t('common.pagination.showing', {
          from: String(from),
          to: String(to),
          total: String(total),
        })}
      </span>
      <div className="row gap-sm">
        <Button variant="secondary" size="sm" onClick={onPrev} disabled={!canPrev}>
          {t('common.buttons.previous')}
        </Button>
        <Button variant="secondary" size="sm" onClick={onNext} disabled={!canNext}>
          {t('common.buttons.next')}
        </Button>
      </div>
    </div>
  );
}
