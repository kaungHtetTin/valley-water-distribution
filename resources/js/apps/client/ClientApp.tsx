import { MobileFoundation } from '../mobile/MobileFoundation';

export function ClientApp() {
    return (
        <MobileFoundation
            applicationLabel="client"
            accentIcon="box"
            nav={[
                { label: 'home', icon: 'activity' },
                { label: 'order', icon: 'box' },
                { label: 'orders', icon: 'route' },
                { label: 'account', icon: 'users' },
            ]}
        />
    );
}
