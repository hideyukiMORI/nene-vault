import { useNavigate } from 'react-router-dom';
import { useTranslation } from '@/shared/i18n/use-translation';
import { BrandMark, Button } from '@/shared/ui';

export function ForbiddenPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  return (
    <div className="center">
      <div className="center-card">
        <div className="head">
          <div className="brand-lock">
            <BrandMark size={40} className="text-seal" title="NeNe Vault" />
          </div>
        </div>
        <div className="body text-center stack-md">
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
