import { useTranslation } from '@/shared/i18n/use-translation';
import { Button } from '@/shared/ui';

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
    <div className="flex items-center justify-between py-stack-sm">
      <span className="text-body-sm text-muted">
        {t('common.pagination.showing', {
          from: String(from),
          to: String(to),
          total: String(total),
        })}
      </span>
      <div className="flex gap-inline-sm">
        <Button variant="secondary" onClick={onPrev} disabled={!canPrev}>
          {t('common.buttons.previous')}
        </Button>
        <Button variant="secondary" onClick={onNext} disabled={!canNext}>
          {t('common.buttons.next')}
        </Button>
      </div>
    </div>
  );
}
