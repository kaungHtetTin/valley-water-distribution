const { contextBridge, ipcRenderer } = require('electron');
contextBridge.exposeInMainWorld('valleyDesktop', Object.freeze({
    platform: 'windows', app: 'office',
    status: () => ipcRenderer.invoke('valley:status'),
    setNotifications: (enabled) => ipcRenderer.invoke('valley:notification-permission', enabled),
    notify: (body, path) => ipcRenderer.send('valley:notify', { body, path }),
}));
