import { Button } from '@hideyukimori/nene2-ui';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from '@/shared/i18n/use-translation';
import { BrandMark } from '@/shared/ui/primitives/BrandMark';

export function ForbiddenPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  return (
    <div className="min-h-screen flex flex-col page-glow">
      <div className="m-auto w-full max-w-98 bg-surface-raised border border-x-line-mid rounded-lg shadow-lg overflow-hidden">
        <div className="pt-7.5 px-7.5 pb-1 text-center">
          <div className="inline-flex flex-col items-center gap-3">
            <BrandMark size={40} className="text-x-seal" title="NeNe Vault" />
          </div>
        </div>
        <div className="pt-6 px-7.5 pb-7.5 text-center space-y-4">
          <p className="danger">{t('problem.forbidden')}</p>
          {/* Escape hatch so a forbidden route is never a dead-end (#174). */}
          <Button
            variant="outline"
            tone="neutral"
            onClick={() => {
              void navigate('/');
            }}
          >
            {t('navigation.back_home')}
          </Button>
        </div>
      </div>
    </div>
  );
}
