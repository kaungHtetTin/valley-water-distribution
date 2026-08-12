import { ClientApp } from './apps/client/ClientApp';
import { DriverApp } from './apps/driver/DriverApp';
import { OfficeApp } from './apps/office/OfficeApp';
import { SalesApp } from './apps/sales/SalesApp';
import type { ApplicationName } from './packages/domain-types/application';
import { applicationPathname } from './packages/routing/basePath';

const applications: Record<ApplicationName, () => React.JSX.Element> = {
    office: OfficeApp,
    sales: SalesApp,
    driver: DriverApp,
    client: ClientApp,
};

export function PlatformApp() {
    const application = applicationPathname().split('/')[1] as ApplicationName;
    const Application = applications[application] ?? OfficeApp;

    return <Application />;
}
