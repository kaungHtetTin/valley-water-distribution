# Android and Windows applications

The apps connect to `https://www.valleymyanmar.com/public` over HTTPS. They need internet access and do not queue offline transactions. The server health endpoint and all four app entry points must respond successfully before distributing a release.

## Packages

| App | Package ID | Platform |
| --- | --- | --- |
| Valley Sales | `com.valleymyanmar.sales` | Android 8 / API 26 and newer |
| Valley Supervisor | `com.valleymyanmar.supervisor` | Android 8 / API 26 and newer |
| Valley Driver | `com.valleymyanmar.driver` | Android 8 / API 26 and newer |
| Valley Office | `com.valleymyanmar.office` | Windows 10/11 x64, portable executable |

Keep Android System WebView / Google Chrome and Google Play services updated, especially on Android 8. The Android project has three product flavors with separate installation IDs, app names, launch URLs, and session storage. They share native source code. Only the Driver manifest declares the location foreground service.

App logos come from `logos/logo_office.png`, `logos/logo_sale_representative.png`, `logos/logo_sale_supervisor.png`, and `logos/logo_driver.png`. The build scripts regenerate the Windows multi-resolution ICO and each Android flavor's launcher, adaptive, and splash images from these source files. The Android assets preserve the full artwork with padding to keep text inside launcher masks. Version 1.0.1 uses Android version code 2 and the existing release signing key, allowing installation over version 1.0.0.

The frontend is served by Laravel, including its runtime URLs, cookies, and CSRF protection. Updating the web server updates the interface in the installed applications. New native features and changes to native permissions require a new APK/EXE. The Capacitor bridge is restricted to the trusted server; release WebView debugging, cleartext connections, and mixed content are disabled.

## Build

Install Node 22+, an Android SDK including platform/build-tools 36, and JDK 21 or 25. The checked-in Gradle 9.3.1 wrapper supports the JDK 25 supplied with current Android Studio. Set `JAVA_HOME` and `ANDROID_HOME` when needed.

```powershell
npm ci
npm run build
npm run android:build
npm run office:build
npm run test:native
```

`npm run android:build` synchronizes Capacitor, generates the three flavor configurations, and signs release APKs. It writes the APKs into `artifacts/android/`. `npm run android:debug` creates separately signed test APKs. Remove a debug install before installing a release with the same package ID.

The Office portable executable is written into `artifacts/windows/`. It runs with the current user's permissions. Sessions and settings stay in the user's Windows application-data directory; moving the EXE does not move those sessions. Downloads use a Save dialog. Existing document printing uses the Windows print dialog. Notifications work while Office is running and are opt-in under Profile & Security.

This build uses Electron 43.8.3, which was already available in the machine's verified runtime cache. Its maintenance branch ends in January 2027. Upgrade to a currently supported Electron version before then; x64 is the only Windows build target. The configuration permits normal Electron upgrades. No Windows code-signing certificate has been supplied, so the portable EXE is unsigned and may show a publisher/SmartScreen prompt.

## Signing keys

The first release build creates `native/secrets/valley-release.jks` and `native/secrets/android-signing.json`. Both are ignored by Git. Back up the entire directory securely. Never upload it into the website, include it in release ZIPs, or commit it. Future APK updates must use the same key. Passwords are passed to keytool and Gradle through the child process environment rather than command-line arguments.

For an existing signing key, provide `VALLEY_ANDROID_KEYSTORE`, `VALLEY_ANDROID_KEY_ALIAS`, `VALLEY_ANDROID_STORE_PASSWORD`, and `VALLEY_ANDROID_KEY_PASSWORD` in the build process environment. The build will reuse it. Increase `versionCode` and `versionName` in `android/app/build.gradle` for each distributed update, and keep the output names in the build script consistent.

## Deploy the server changes before enabling native features

Deploy the changed Laravel files, the new migration, and the complete rebuilt `public/build/` directory using the normal project deployment procedure. Do not deploy `.env`, the native signing directory, the Android SDK, or node_modules. No production deployment is performed by the local build scripts.

```sh
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
```

The new endpoints are:

- Session-authenticated, CSRF-protected `POST /api/native/devices` and `DELETE /api/native/devices/{installationId}`.
- GPS-only bearer endpoint `POST /api/native/tracking`; it accepts no business API authentication and resolves the active driver/trip on the server.

URLs retain the `/public` deployment prefix. Device registration validates the user's allowed app. FCM tokens are encrypted in the database, tracking credentials are stored only as hashes, and device registration credentials are excluded from mutation audit payloads. Logout revokes devices belonging to the current session, and password reset revokes the user's devices. Every GPS upload checks the account, role permissions, employee assignment, active trip, and credential expiration. Tracking credentials last at most 24 hours and are refreshed while the app is open. Push registrations expire after 30 days without app use. Reopen the app to renew them.

## Driver background location

In Valley Driver, open **Profile & Security → Device permissions → Enable trip tracking**. Read the explanation, allow notifications, grant precise location, and turn on device location. Approximate-only location is rejected for trip tracking with a Settings recovery action. Other screens remain usable when a permission is denied.

Once enabled, the visible app starts the native location foreground service when an assigned trip becomes `on_route` (checked immediately when the trip screen changes and periodically while the app is visible). The service continues through screen lock and app minimization. It shows a persistent notification with a **Stop tracking** action. It uploads fresh GPS points about every 20 seconds and discards points when disconnected rather than queuing them. It stops when the server reports no active trip, the credential expires/is revoked, or the user signs out or stops tracking.

The service starts while the app is visible, so Android's location foreground-service permissions provide ongoing access without requesting unrestricted `ACCESS_BACKGROUND_LOCATION`. Sales and Supervisor do not have the background tracking service permission. No boot receiver, permanent wake lock, storage permission, microphone permission, or automatic battery-optimization exemption is requested.

Android force-stop, process termination, OEM battery management, revoked permissions, and disabled GPS can interrupt tracking. Reopen Valley Driver to resume; force-stop does not automatically restart it. Test the target phone models with the screen locked during an active trip. Native tracking uploads independently of the WebView. The GPS map can still show foreground positions without creating a second upload stream. Native document printing uses Android's normal Print/Save PDF interface.

## Activate Firebase push later

Current APKs can be built and used without Firebase. Push remains explicitly unavailable until Firebase is configured; in-app alerts still work.

1. Create one Firebase project and register three Android applications using the exact package IDs above.
2. Download each app's `google-services.json` into its matching ignored directory: `android/app/src/sales/`, `android/app/src/supervisor/`, and `android/app/src/driver/`. All three files are needed to activate the Google Services build plugin.
3. Enable the Firebase Cloud Messaging HTTP v1 API. Create a service account with permission to send Firebase Cloud Messaging messages.
4. Store the service-account JSON outside the server's public web directory and outside this repository. Set the server-only `FIREBASE_CREDENTIALS` environment value to its absolute path, then refresh the Laravel configuration cache. Never put this service account in an Android app or EXE.
5. Rebuild signed APKs with an increased Android `versionCode` using the same signing key; install those updates.
6. In each app's Profile & Security settings, choose **Enable notifications**. Android 13+ requests notification permission at this point. Denial has an Android Settings recovery button. Devices without Google Play services cannot receive FCM notifications.
7. Configure the production scheduler to run `php artisan schedule:run` every minute if it is not already configured. The scheduled `valley:push-alerts` command sends notifications for changed nonzero action-queue counts. Run `php artisan valley:push-alerts` manually for the initial server check.

Push notifications use the existing scoped action queues: Sales draft orders, Supervisor customer approvals, and Driver delivery tasks. They are deduplicated by queue ID and count; they are queue-change reminders rather than a complete event history. Changes that leave a queue count unchanged do not send another reminder. Notifications carry an app-specific path and require sign-in for business data. The server rechecks role and employee access on each scheduled send. Permission/token changes sync when the app is next opened; provider token rotation while the app is closed may require reopening the app before delivery resumes.

## Release checks

- Verify all APK signatures and package IDs with Android `apksigner` and `aapt`; check minimum SDK 26 and target SDK 36.
- Check device permissions in denied, permanently denied, approximate-only, precise, and location-disabled states.
- Check route start, minimize, screen lock, task switching, trip completion, logout, expired credentials, and access revocation on real devices.
- Verify notification receipt and taps both while open and minimized after Firebase setup. Verify another user's device cannot receive a previous account's alerts after sign-out.
- Check Office login, role restrictions, generated document printing, Save dialogs, and external links with Node integration disabled.
- Test Android Back through lists/details/forms and verify source filters and scroll state are preserved.
- Run `php artisan test --compact --filter='NativeDeviceTest|ActionAlertTest'` and `npm run test:native`, then rebuild the frontend and release artifacts after any code change.

The local build and automated checks do not replace the real-device background tracking and Firebase delivery checks above. Production deployment and Firebase configuration are separate from generating the packages.
