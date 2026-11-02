export function driverRoute(pathname, appPath, mode = 'tasks') {
    const app = new URL(appPath, 'http://valley.local').pathname.replace(/\/$/, '');
    const base = `${app}/${mode === 'history' ? 'history' : 'tasks'}`;
    const suffix = pathname === base ? '' : pathname.startsWith(`${base}/`) ? pathname.slice(base.length + 1) : '';
    const match = suffix.match(/^(\d+)(?:\/(closeout)|\/stops\/(\d+))?\/?$/);
    return { base, tripId: match?.[1] || null, closeout: Boolean(match?.[2]), stopId: match?.[3] || null, invalid: Boolean(suffix && !match) };
}
