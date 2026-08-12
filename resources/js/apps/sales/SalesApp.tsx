import { MobileFoundation } from '../mobile/MobileFoundation';

export function SalesApp() {
    return (
        <MobileFoundation
            applicationLabel="sales"
            accentIcon="users"
            nav={[
                { label: 'today', icon: 'activity' },
                { label: 'clients', icon: 'users' },
                { label: 'order', icon: 'box' },
                { label: 'kpi', icon: 'activity' },
                { label: 'more', icon: 'menu' },
            ]}
        />
    );
}
