package com.valleymyanmar.sales;

import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;
import android.webkit.GeolocationPermissions;
import android.webkit.PermissionRequest;
import android.webkit.WebResourceRequest;
import android.webkit.WebView;
import androidx.activity.OnBackPressedCallback;
import com.getcapacitor.BridgeActivity;
import com.getcapacitor.BridgeWebChromeClient;
import com.getcapacitor.BridgeWebViewClient;

public class MainActivity extends BridgeActivity {
    public static boolean trusted(String value) {
        if (value == null) return false;
        Uri uri = Uri.parse(value);
        return "https".equals(uri.getScheme()) && "www.valleymyanmar.com".equals(uri.getHost())
            && (uri.getPort() == -1 || uri.getPort() == 443) && uri.getUserInfo() == null;
    }

    private boolean appPage(String value) {
        if (!trusted(value)) return false;
        String path = Uri.parse(value).getPath();
        for (String prefix : new String[]{"/public/" + BuildConfig.APP_SLUG, "/public/attendance", "/public/reset-password"}) {
            if (path.equals(prefix) || path.startsWith(prefix + "/")) return true;
        }
        return false;
    }

    @Override public void onCreate(Bundle state) {
        registerPlugin(ValleyNativePlugin.class);
        super.onCreate(state);
        if (bridge == null) return;
        WebView web = bridge.getWebView();
        web.getSettings().setAllowFileAccess(false);
        web.getSettings().setAllowContentAccess(false);
        web.getSettings().setMixedContentMode(android.webkit.WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        WebView.setWebContentsDebuggingEnabled(BuildConfig.DEBUG);
        bridge.setWebViewClient(new BridgeWebViewClient(bridge) {
            @Override public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                String value = request.getUrl().toString();
                if (appPage(value)) return false;
                if (!trusted(value) && request.hasGesture() && "https".equals(request.getUrl().getScheme())) {
                    try { startActivity(new Intent(Intent.ACTION_VIEW, request.getUrl())); } catch (Exception ignored) {}
                }
                return true;
            }
            @Override public void onPageStarted(WebView view, String value, android.graphics.Bitmap favicon) {
                if (!appPage(value) && !value.equals(bridge.getErrorUrl())) {
                    view.stopLoading();
                    view.loadUrl(BuildConfig.SERVER_BASE + "/" + BuildConfig.APP_SLUG);
                    return;
                }
                super.onPageStarted(view, value, favicon);
            }
        });
        web.setWebChromeClient(new BridgeWebChromeClient(bridge) {
            @Override public void onPermissionRequest(PermissionRequest request) { request.deny(); }
            @Override public void onGeolocationPermissionsShowPrompt(String origin, GeolocationPermissions.Callback callback) {
                if (!trusted(origin) || !appPage(web.getUrl())) { callback.invoke(origin, false, false); return; }
                super.onGeolocationPermissionsShowPrompt(origin, callback);
            }
        });
        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override public void handleOnBackPressed() {
                if (web.canGoBack()) web.goBack(); else moveTaskToBack(true);
            }
        });
        ValleyMessagingService.createChannel(this);
        openNotification(getIntent());
    }

    @Override protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        openNotification(intent);
    }

    private void openNotification(Intent intent) {
        if (intent == null || bridge == null) return;
        String path = intent.getStringExtra("path");
        if (path != null && path.matches("/[a-zA-Z0-9/_-]{0,200}")) {
            bridge.getWebView().loadUrl(BuildConfig.SERVER_BASE + "/" + BuildConfig.APP_SLUG + path);
            intent.removeExtra("path");
        }
    }
}
