package com.valleymyanmar.sales;

import android.Manifest;
import android.app.NotificationManager;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.location.LocationManager;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.provider.Settings;
import android.print.PrintManager;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import androidx.core.app.NotificationManagerCompat;
import androidx.core.content.ContextCompat;
import androidx.core.location.LocationManagerCompat;
import com.getcapacitor.*;
import com.getcapacitor.annotation.*;
import com.google.firebase.FirebaseApp;
import com.google.firebase.messaging.FirebaseMessaging;
import java.util.UUID;

@CapacitorPlugin(name = "ValleyNative", permissions = {
    @Permission(alias = "location", strings = {Manifest.permission.ACCESS_COARSE_LOCATION, Manifest.permission.ACCESS_FINE_LOCATION}),
    @Permission(alias = "notifications", strings = {Manifest.permission.POST_NOTIFICATIONS})
})
public class ValleyNativePlugin extends Plugin {
    private void withTrustedPage(PluginCall call, Runnable action) {
        // Capacitor invokes plugins on its worker thread; WebView belongs to the UI thread.
        getBridge().executeOnMainThread(() -> {
            WebView web = getBridge().getWebView();
            if (getActivity().isFinishing() || getActivity().isDestroyed() || web == null
                || !MainActivity.trusted(web.getUrl())) {
                call.reject("Untrusted application page.");
                return;
            }
            // Keep credential storage and other native work on Capacitor's worker thread.
            getBridge().execute(action);
        });
    }
    private boolean fine() { return ContextCompat.checkSelfPermission(getContext(), Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED; }
    private boolean firebase() { return !FirebaseApp.getApps(getContext()).isEmpty(); }
    private boolean locationEnabled() { return LocationManagerCompat.isLocationEnabled((LocationManager) getContext().getSystemService(Context.LOCATION_SERVICE)); }

    @PluginMethod public void status(PluginCall call) {
        withTrustedPage(call, () -> {
            String installation = SecureStore.prefs(getContext()).getString("installation", null);
            if (installation == null) {
                installation = UUID.randomUUID().toString();
                SecureStore.prefs(getContext()).edit().putString("installation", installation).apply();
            }
            JSObject result = new JSObject();
            result.put("app", BuildConfig.APP_SLUG);
            result.put("installationId", installation);
            result.put("notifications", NotificationManagerCompat.from(getContext()).areNotificationsEnabled());
            result.put("pushConfigured", firebase());
            result.put("fcmToken", SecureStore.get(getContext(), "fcm_token"));
            result.put("locationGranted", fine());
            result.put("locationEnabled", locationEnabled());
            result.put("trackingEnabled", SecureStore.prefs(getContext()).getBoolean("tracking_consent", false));
            result.put("trackingRunning", DriverLocationService.running);
            result.put("lastUpload", SecureStore.prefs(getContext()).getLong("last_upload", 0));
            result.put("trackingError", SecureStore.prefs(getContext()).getString("tracking_error", ""));
            call.resolve(result);
        });
    }

    @PluginMethod public void configure(PluginCall call) {
        withTrustedPage(call, () -> {
            String token = call.getString("trackingToken");
            if (!"driver".equals(BuildConfig.APP_SLUG)) { call.resolve(); return; }
            if (token == null || token.length() != 80) { call.reject("Invalid tracking credential."); return; }
            try { SecureStore.put(getContext(), "tracking_token", token); call.resolve(); }
            catch (Exception ignored) { call.reject("Unable to protect this device credential."); }
        });
    }

    @PluginMethod public void enableNotifications(PluginCall call) {
        withTrustedPage(call, () -> {
            if (!firebase()) { call.reject("Push notifications need Firebase setup. Other app features remain available."); return; }
            if (Build.VERSION.SDK_INT >= 33 && getPermissionState("notifications") != PermissionState.GRANTED) {
                requestPermissionForAlias("notifications", call, "notificationResult");
            } else notificationResult(call);
        });
    }
    @PermissionCallback private void notificationResult(PluginCall call) {
        if (!NotificationManagerCompat.from(getContext()).areNotificationsEnabled()) { call.reject("Notifications are disabled. Enable them in Android Settings."); return; }
        ValleyMessagingService.createChannel(getContext());
        FirebaseMessaging.getInstance().getToken().addOnCompleteListener(task -> {
            if (!task.isSuccessful()) { call.reject("Push registration failed. Check your internet connection and Google Play services."); return; }
            try { SecureStore.put(getContext(), "fcm_token", task.getResult()); }
            catch (Exception ignored) { call.reject("Unable to save push registration."); return; }
            JSObject result = new JSObject(); result.put("fcmToken", task.getResult()); call.resolve(result);
        });
    }

    @PluginMethod public void enableTracking(PluginCall call) {
        withTrustedPage(call, () -> {
            if (!"driver".equals(BuildConfig.APP_SLUG)) { call.reject("Trip tracking is only available in Valley Driver."); return; }
            if (Build.VERSION.SDK_INT >= 33 && getPermissionState("notifications") != PermissionState.GRANTED) {
                requestPermissionForAlias("notifications", call, "trackingNotificationResult");
            } else trackingNotificationResult(call);
        });
    }
    @PermissionCallback private void trackingNotificationResult(PluginCall call) {
        if (!NotificationManagerCompat.from(getContext()).areNotificationsEnabled()) { call.reject("Allow notifications in Android Settings so active trip tracking stays visible."); return; }
        if (!fine()) requestPermissionForAlias("location", call, "locationResult");
        else locationResult(call);
    }
    @PermissionCallback private void locationResult(PluginCall call) {
        if (!fine()) { call.reject("Precise location is needed for delivery tracking. Choose precise location in Android Settings."); return; }
        if (!locationEnabled()) { call.reject("Turn on device location in Android Settings, then try again."); return; }
        SecureStore.prefs(getContext()).edit().putBoolean("tracking_consent", true).apply();
        call.resolve();
    }

    @PluginMethod public void startTracking(PluginCall call) {
        withTrustedPage(call, () -> {
            if (!"driver".equals(BuildConfig.APP_SLUG) || !fine() || !locationEnabled()
                || !SecureStore.prefs(getContext()).getBoolean("tracking_consent", false)
                || SecureStore.get(getContext(), "tracking_token") == null) { call.reject("Enable trip tracking and sign in before starting."); return; }
            if (!getActivity().getLifecycle().getCurrentState().isAtLeast(androidx.lifecycle.Lifecycle.State.RESUMED)) { call.reject("Open Valley Driver to start trip tracking."); return; }
            try {
                ContextCompat.startForegroundService(getContext(), new Intent(getContext(), DriverLocationService.class));
                call.resolve();
            } catch (Exception ignored) { call.reject("Android could not start tracking. Reopen the app and check location permissions."); }
        });
    }

    @PluginMethod public void stopTracking(PluginCall call) {
        withTrustedPage(call, () -> {
            getContext().stopService(new Intent(getContext(), DriverLocationService.class));
            SecureStore.prefs(getContext()).edit().putBoolean("tracking_consent", false).apply();
            call.resolve();
        });
    }

    @PluginMethod public void disconnect(PluginCall call) {
        withTrustedPage(call, () -> {
            getContext().stopService(new Intent(getContext(), DriverLocationService.class));
            SecureStore.prefs(getContext()).edit().remove("tracking_token").remove("fcm_token").remove("last_upload").remove("tracking_error").putBoolean("tracking_consent", false).apply();
            if (firebase()) FirebaseMessaging.getInstance().deleteToken();
            NotificationManagerCompat.from(getContext()).cancelAll();
            call.resolve();
        });
    }

    @PluginMethod public void openSettings(PluginCall call) {
        withTrustedPage(call, () -> {
            getActivity().startActivity(new Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, Uri.parse("package:" + getContext().getPackageName())));
            call.resolve();
        });
    }

    @PluginMethod public void printHtml(PluginCall call) {
        withTrustedPage(call, () -> {
            String html = call.getString("html");
            if (html == null || html.length() > 2_000_000) { call.reject("Print document is too large."); return; }
            getActivity().runOnUiThread(() -> {
                WebView printView = new WebView(getContext());
                printView.getSettings().setJavaScriptEnabled(false);
                printView.getSettings().setAllowFileAccess(false);
                printView.getSettings().setAllowContentAccess(false);
                printView.getSettings().setBlockNetworkLoads(true);
                printView.setWebViewClient(new WebViewClient() {
                    @Override public void onPageFinished(WebView view, String url) {
                        PrintManager manager = (PrintManager) getContext().getSystemService(Context.PRINT_SERVICE);
                        android.print.PrintDocumentAdapter adapter = view.createPrintDocumentAdapter("Valley document");
                        manager.print("Valley document", new android.print.PrintDocumentAdapter() {
                            @Override public void onLayout(android.print.PrintAttributes oldAttributes, android.print.PrintAttributes newAttributes, android.os.CancellationSignal cancellation, LayoutResultCallback callback, Bundle extras) { adapter.onLayout(oldAttributes, newAttributes, cancellation, callback, extras); }
                            @Override public void onWrite(android.print.PageRange[] pages, android.os.ParcelFileDescriptor destination, android.os.CancellationSignal cancellation, WriteResultCallback callback) { adapter.onWrite(pages, destination, cancellation, callback); }
                            @Override public void onStart() { adapter.onStart(); }
                            @Override public void onFinish() { adapter.onFinish(); printView.destroy(); }
                        }, null);
                        call.resolve();
                    }
                });
                printView.loadDataWithBaseURL(BuildConfig.SERVER_BASE, html, "text/html", "UTF-8", null);
            });
        });
    }
}
