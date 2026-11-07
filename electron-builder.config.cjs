module.exports = {
    appId: 'com.valleymyanmar.office', productName: 'Valley Office',
    directories: { app: 'native/electron', output: 'artifacts/windows' },
    files: ['main.cjs', 'preload.cjs', 'policy.cjs', 'package.json', 'valley.ico', '!node_modules/**'],
    asar: true,
    electronDist: require('node:path').join(__dirname, 'node_modules/electron/dist'),
    electronFuses: {
        runAsNode: false, enableCookieEncryption: true,
        enableNodeOptionsEnvironmentVariable: false, enableNodeCliInspectArguments: false,
        enableEmbeddedAsarIntegrityValidation: true, onlyLoadAppFromAsar: true,
        grantFileProtocolExtraPrivileges: false,
    },
    win: { icon: 'native/electron/valley.ico', target: [{ target: 'portable', arch: ['x64'] }], requestedExecutionLevel: 'asInvoker', artifactName: 'Valley-Office-${version}-win-x64-portable.${ext}' },
    portable: { requestExecutionLevel: 'user' },
};
