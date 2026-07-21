import { useNavigate } from 'react-router-dom';
import { useTranslation } from '@/shared/i18n/use-translation';
import { BrandMark } from '@/shared/ui/primitives/BrandMark';
import { Button } from '@/shared/ui/primitives/Button';

export function ForbiddenPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  return (
    <div className="min-h-screen flex flex-col page-glow">
      <div className="center-card">
        <div className="head">
          <div className="inline-flex flex-col items-center gap-3">
            <BrandMark size={40} className="text-x-seal" title="NeNe Vault" />
          </div>
        </div>
        <div className="body text-center space-y-4">
          <p className="danger">{t('problem.forbidden')}</p>
          {/* Escape hatch so a forbidden route is never a dead-end (#174). */}
          <Button
            variant="secondary"
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
