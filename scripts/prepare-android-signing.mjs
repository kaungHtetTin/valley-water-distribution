import { mkdir, readFile, writeFile, access } from 'node:fs/promises';
import { randomBytes } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
const root = fileURLToPath(new URL('../', import.meta.url));
const secrets = path.join(root, 'native', 'secrets');
const settings = path.join(secrets, 'android-signing.json');
await mkdir(secrets, { recursive: true });
try {
    const existing = JSON.parse(await readFile(settings, 'utf8'));
    await access(existing.keystore);
    console.log('Using existing local Android release signing key.');
} catch (error) {
    if (error.code !== 'ENOENT') throw error;
    const keystore = path.join(secrets, 'valley-release.jks');
    // Refuse to replace an existing key whose password/settings file was lost.
    let existingKey = false;
    try { await access(keystore); existingKey = true; } catch {}
    if (existingKey) throw new Error('Release key already exists. Restore android-signing.json from your backup.');
    const password = randomBytes(32).toString('base64url');
    const result = spawnSync(path.join(process.env.JAVA_HOME, 'bin', 'keytool.exe'), [
        '-genkeypair', '-keystore', keystore, '-storetype', 'PKCS12', '-alias', 'valley-release',
        '-keyalg', 'RSA', '-keysize', '3072', '-validity', '10000',
        '-dname', 'CN=Valley Water Distribution, O=Valley Water Distribution, C=MM',
        '-storepass:env', 'VALLEY_GENERATED_KEY_PASSWORD', '-keypass:env', 'VALLEY_GENERATED_KEY_PASSWORD',
    ], { env: { ...process.env, VALLEY_GENERATED_KEY_PASSWORD: password }, stdio: 'inherit', windowsHide: true });
    if (result.status !== 0) throw new Error('Unable to generate Android signing key.');
    await writeFile(settings, JSON.stringify({ keystore, alias: 'valley-release', password }, null, 2), { flag: 'wx' });
    console.log('Created local Android release key. Back up native/secrets securely; future APK updates need this key.');
}
