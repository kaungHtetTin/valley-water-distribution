import { Link, useLocation } from 'react-router-dom';
import { useI18n } from '../../../packages/i18n/I18nProvider';

export function MasterDataNavigation() {
    const { pathname } = useLocation();
    const { t } = useI18n();

    return (
        <nav className="section-tabs" aria-label={t('masterDataSections')}>
            <Link className={!pathname.includes('/catalog') && !pathname.includes('/ways') && !pathname.includes('/locations') && !pathname.includes('/controls') && !pathname.includes('/storage') && !pathname.includes('/route-templates') && !pathname.includes('/foundation') && !pathname.includes('/governance') ? 'is-active' : ''} to="/office/master-data">{t('areas')}</Link>
            <Link className={pathname.includes('/ways') ? 'is-active' : ''} to="/office/master-data/ways">{t('ways')}</Link>
            <Link className={pathname.includes('/route-templates') ? 'is-active' : ''} to="/office/master-data/route-templates">{t('routeTemplates')}</Link>
            <Link className={pathname === '/office/master-data/catalog' ? 'is-active' : ''} to="/office/master-data/catalog">{t('catalogAndPricing')}</Link>
            <Link className={pathname.includes('/catalog-setup') ? 'is-active' : ''} to="/office/master-data/catalog-setup">{t('catalogSetup')}</Link>
            <Link className={pathname.includes('/locations') ? 'is-active' : ''} to="/office/master-data/locations">{t('locations')}</Link>
            <Link className={pathname.includes('/controls') ? 'is-active' : ''} to="/office/master-data/controls">{t('organizationControls')}</Link>
            <Link className={pathname.includes('/storage') ? 'is-active' : ''} to="/office/master-data/storage">{t('storageAndCash')}</Link>
            <Link className={pathname.includes('/foundation') ? 'is-active' : ''} to="/office/master-data/foundation">{t('foundationMasters')}</Link>
            <Link className={pathname.includes('/governance') ? 'is-active' : ''} to="/office/master-data/governance">{t('governanceAndPricing')}</Link>
        </nav>
    );
}
