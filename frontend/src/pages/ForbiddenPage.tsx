import { useTranslation } from '@/shared/i18n/use-translation';
import { BrandMark } from '@/shared/ui';

export function ForbiddenPage() {
  const { t } = useTranslation();
  return (
    <div className="center">
      <div className="center-card">
        <div className="head">
          <div className="brand-lock">
            <BrandMark size={40} className="text-seal" title="NeNe Vault" />
          </div>
        </div>
        <div className="body text-center">
          <p className="danger">{t('problem.forbidden')}</p>
        </div>
      </div>
    </div>
  );
}
