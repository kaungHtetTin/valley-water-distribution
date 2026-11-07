package com.valleymyanmar.sales;

import android.app.*;
import android.content.Context;
import android.content.Intent;
import androidx.core.app.NotificationCompat;
import androidx.core.app.NotificationManagerCompat;
import com.google.firebase.messaging.FirebaseMessagingService;
import com.google.firebase.messaging.RemoteMessage;

public class ValleyMessagingService extends FirebaseMessagingService {
    static void createChannel(Context context) {
        NotificationChannel channel = new NotificationChannel("valley_alerts", "Valley updates", NotificationManager.IMPORTANCE_DEFAULT);
        channel.setLockscreenVisibility(Notification.VISIBILITY_PRIVATE);
        ((NotificationManager) context.getSystemService(Context.NOTIFICATION_SERVICE)).createNotificationChannel(channel);
    }
    @Override public void onNewToken(String token) {
        try { SecureStore.put(this, "fcm_token", token); } catch (Exception ignored) {}
    }
    @Override public void onMessageReceived(RemoteMessage message) {
        if (!BuildConfig.APP_SLUG.equals(message.getData().get("app"))) return;
        if (!NotificationManagerCompat.from(this).areNotificationsEnabled()) return;
        createChannel(this);
        Intent intent = new Intent(this, MainActivity.class).addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP);
        String path = message.getData().get("path");
        if (path != null && path.matches("/[a-zA-Z0-9/_-]{0,200}")) intent.putExtra("path", path);
        PendingIntent open = PendingIntent.getActivity(this, 10, intent, PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE);
        String body = message.getNotification() == null ? "Open Valley to review your updates." : message.getNotification().getBody();
        Notification notification = new NotificationCompat.Builder(this, "valley_alerts").setSmallIcon(R.drawable.ic_notification)
            .setContentTitle(getString(R.string.app_name)).setContentText(body).setContentIntent(open).setAutoCancel(true)
            .setVisibility(NotificationCompat.VISIBILITY_PRIVATE).build();
        try { NotificationManagerCompat.from(this).notify(200, notification); } catch (SecurityException ignored) {}
    }
}
