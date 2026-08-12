import { MobileFoundation } from '../mobile/MobileFoundation';

export function DriverApp() {
    return (
        <MobileFoundation
            applicationLabel="driver"
            accentIcon="truck"
            nav={[
                { label: 'trip', icon: 'truck' },
                { label: 'stops', icon: 'route' },
                { label: 'map', icon: 'route' },
                { label: 'more', icon: 'menu' },
            ]}
        />
    );
}
