import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
const cwd = fileURLToPath(new URL('../', import.meta.url));
const env = { ...process.env, ELECTRON_BUILDER_COMPRESSION_LEVEL: '5' };
delete env.ELECTRON_RUN_AS_NODE;
const icons = spawnSync('powershell.exe', ['-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', 'scripts/prepare-app-icons.ps1', '-Target', 'Office'], { cwd, env, stdio: 'inherit', windowsHide: true });
if (icons.status !== 0) process.exit(icons.status || 1);
for (const args of [
    ['node_modules/electron/install.js'],
    ['node_modules/electron-builder/cli.js', '--config', 'electron-builder.config.cjs', '--win', 'portable', '--x64'],
]) {
    const result = spawnSync(process.execPath, args, { cwd, env, stdio: 'inherit', windowsHide: true });
    if (result.status !== 0) process.exit(result.status || 1);
}
