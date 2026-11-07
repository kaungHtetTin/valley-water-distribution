package com.valleymyanmar.sales;

import android.Manifest;
import android.app.*;
import android.content.*;
import android.content.pm.PackageManager;
import android.content.pm.ServiceInfo;
import android.location.Location;
import android.os.*;
import androidx.core.app.NotificationCompat;
import androidx.core.content.ContextCompat;
import com.google.android.gms.location.*;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.concurrent.*;
import javax.net.ssl.HttpsURLConnection;
import org.json.JSONObject;

public class DriverLocationService extends Service {
    public static volatile boolean running = false;
    private FusedLocationProviderClient locations;
    private LocationCallback callback;
    private ScheduledExecutorService uploader;
    private volatile Location latest;
    private static final String STOP = "valley.STOP_TRACKING";

    @Override public void onCreate() {
        super.onCreate();
        NotificationChannel channel = new NotificationChannel("valley_tracking", "Active trip tracking", NotificationManager.IMPORTANCE_LOW);
        ((NotificationManager) getSystemService(NOTIFICATION_SERVICE)).createNotificationChannel(channel);
    }
    @Override public int onStartCommand(Intent intent, int flags, int startId) {
        if (intent != null && STOP.equals(intent.getAction())) {
            SecureStore.prefs(this).edit().putBoolean("tracking_consent", false).apply();
            stopSelf(); return START_NOT_STICKY;
        }
        if (!"driver".equals(BuildConfig.APP_SLUG) || !SecureStore.prefs(this).getBoolean("tracking_consent", false)
            || ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) != PackageManager.PERMISSION_GRANTED
            || SecureStore.get(this, "tracking_token") == null) { stopSelf(); return START_NOT_STICKY; }
        if (running) return START_NOT_STICKY;
        Intent open = new Intent(this, MainActivity.class);
        PendingIntent openApp = PendingIntent.getActivity(this, 0, open, PendingIntent.FLAG_IMMUTABLE | PendingIntent.FLAG_UPDATE_CURRENT);
        PendingIntent stop = PendingIntent.getService(this, 1, new Intent(this, DriverLocationService.class).setAction(STOP), PendingIntent.FLAG_IMMUTABLE | PendingIntent.FLAG_UPDATE_CURRENT);
        Notification notification = new NotificationCompat.Builder(this, "valley_tracking").setSmallIcon(R.drawable.ic_notification)
            .setContentTitle("Valley Driver trip tracking").setContentText("Sharing location during your active delivery trip.")
            .setOngoing(true).setContentIntent(openApp).setVisibility(NotificationCompat.VISIBILITY_PRIVATE)
            .addAction(R.drawable.ic_notification, "Stop tracking", stop).build();
        try {
            if (Build.VERSION.SDK_INT >= 29) startForeground(100, notification, ServiceInfo.FOREGROUND_SERVICE_TYPE_LOCATION);
            else startForeground(100, notification);
            running = true;
            SecureStore.prefs(this).edit().putString("tracking_error", "").apply();
            locations = LocationServices.getFusedLocationProviderClient(this);
            callback = new LocationCallback() {
                @Override public void onLocationResult(LocationResult result) { latest = result.getLastLocation(); }
                @Override public void onLocationAvailability(LocationAvailability result) {
                    if (!result.isLocationAvailable()) error("Waiting for GPS. Check device location settings.");
                }
            };
            LocationRequest request = new LocationRequest.Builder(Priority.PRIORITY_HIGH_ACCURACY, 15000)
                .setMinUpdateIntervalMillis(10000).setMinUpdateDistanceMeters(5).setMaxUpdateDelayMillis(30000).build();
            locations.requestLocationUpdates(request, callback, Looper.getMainLooper())
                .addOnFailureListener(exception -> { error("Location is unavailable. Reopen the app and check GPS permissions."); stopSelf(); });
            uploader = Executors.newSingleThreadScheduledExecutor();
            uploader.scheduleWithFixedDelay(this::upload, 2, 20, TimeUnit.SECONDS);
        } catch (SecurityException exception) {
            error("Location permission was removed. Open app settings to enable it."); stopSelf();
        }
        // Android force-stop or a killed process requires the user to reopen the app.
        // Never silently restart a location foreground service from the background.
        return START_NOT_STICKY;
    }

    private void error(String value) { SecureStore.prefs(this).edit().putString("tracking_error", value).apply(); }
    private void upload() {
        if (!running) return;
        String token = SecureStore.get(this, "tracking_token");
        if (token == null) { stopSelf(); return; }
        HttpsURLConnection connection = null;
        try {
            JSONObject point = new JSONObject();
            Location location = latest;
            if (location != null && SystemClock.elapsedRealtimeNanos() - location.getElapsedRealtimeNanos() < TimeUnit.MINUTES.toNanos(2)
                && location.hasAccuracy() && location.getAccuracy() <= 10000) {
                point.put("latitude", location.getLatitude()); point.put("longitude", location.getLongitude());
                point.put("accuracy_m", location.getAccuracy());
                if (location.hasBearing()) point.put("heading", location.getBearing());
                if (location.hasSpeed()) point.put("speed_kmh", Math.min(location.getSpeed() * 3.6, 500));
            }
            connection = (HttpsURLConnection) new URL(BuildConfig.SERVER_BASE + "/api/native/tracking").openConnection();
            connection.setInstanceFollowRedirects(false);
            connection.setConnectTimeout(10000); connection.setReadTimeout(10000);
            connection.setRequestMethod("POST"); connection.setDoOutput(true);
            connection.setRequestProperty("Authorization", "Bearer " + token);
            connection.setRequestProperty("Content-Type", "application/json"); connection.setRequestProperty("Accept", "application/json");
            try (java.io.OutputStream stream = connection.getOutputStream()) { stream.write(point.toString().getBytes(StandardCharsets.UTF_8)); }
            int status = connection.getResponseCode();
            if (status == 401 || status == 403) {
                SecureStore.prefs(this).edit().remove("tracking_token").putBoolean("tracking_consent", false).apply();
                error("Tracking session expired. Sign in and enable tracking again."); stopSelf(); return;
            }
            if (status == 200) {
                // A successful 200 indicates no active trip. Actual location saves return 201.
                stopSelf(); return;
            }
            if (status == 409) { stopSelf(); return; }
            if (status == 201) {
                SecureStore.prefs(this).edit().putLong("last_upload", System.currentTimeMillis()).putString("tracking_error", "").apply();
            } else if (status == 422) error("Waiting for an accurate GPS position.");
            else error("Location upload unavailable. Retrying while the trip is active.");
        } catch (Exception ignored) { error("No connection. Location updates resume when internet returns."); }
        finally { if (connection != null) connection.disconnect(); }
    }

    @Override public void onDestroy() {
        running = false;
        if (locations != null && callback != null) locations.removeLocationUpdates(callback);
        if (uploader != null) uploader.shutdownNow();
        stopForeground(STOP_FOREGROUND_REMOVE);
        super.onDestroy();
    }
    @Override public IBinder onBind(Intent intent) { return null; }
}
