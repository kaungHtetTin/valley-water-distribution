package com.valleymyanmar.sales;

import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.net.http.SslError;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;
import android.webkit.SslErrorHandler;
import android.webkit.WebResourceError;
import android.webkit.WebResourceResponse;
import android.webkit.GeolocationPermissions;
import android.webkit.PermissionRequest;
import android.webkit.WebResourceRequest;
import android.webkit.WebView;
import androidx.activity.OnBackPressedCallback;
import com.getcapacitor.BridgeActivity;
import com.getcapacitor.BridgeWebChromeClient;
import com.getcapacitor.BridgeWebViewClient;

public class MainActivity extends BridgeActivity {
    private final Handler connectionHandler = new Handler(Looper.getMainLooper());
    private View connectionOverlay;
    private Runnable readinessProbe;
    private Runnable connectionTimeout;
    private int connectionGeneration;
    private boolean connectionFailed;
    private boolean documentCommitted;

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
        web.setBackgroundColor(android.graphics.Color.WHITE);
        connectionOverlay = getLayoutInflater().inflate(R.layout.native_connection_overlay, null);
        addContentView(connectionOverlay, new ViewGroup.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
        connectionOverlay.findViewById(R.id.connection_retry).setOnClickListener(button -> {
            beginLoading(web);
            web.loadUrl(BuildConfig.SERVER_BASE + "/" + BuildConfig.APP_SLUG);
        });
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
                beginLoading(view);
                super.onPageStarted(view, value, favicon);
            }
            @Override public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
                if (request.isForMainFrame()) {
                    android.util.Log.w("ValleyConnection", "Page load failed with code " + error.getErrorCode());
                    showConnectionError(R.string.connection_failed);
                }
                else super.onReceivedError(view, request, error);
            }
            @Override public void onReceivedHttpError(WebView view, WebResourceRequest request, WebResourceResponse response) {
                if (request.isForMainFrame()) {
                    android.util.Log.w("ValleyConnection", "Page returned HTTP " + response.getStatusCode());
                    showConnectionError(R.string.connection_failed);
                }
                else super.onReceivedHttpError(view, request, response);
            }
            @Override public void onReceivedSslError(WebView view, SslErrorHandler handler, SslError error) {
                handler.cancel();
                showConnectionError(R.string.connection_secure_failed);
            }
            @Override public void onPageCommitVisible(WebView view, String url) {
                super.onPageCommitVisible(view, url);
                documentCommitted = true;
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
        beginLoading(web);
        openNotification(getIntent());
    }

    private void beginLoading(WebView web) {
        if (connectionOverlay == null) return;
        cancelConnectionChecks();
        final int generation = ++connectionGeneration;
        connectionFailed = false;
        documentCommitted = false;
        connectionOverlay.setVisibility(View.VISIBLE);
        ((TextView) connectionOverlay.findViewById(R.id.connection_title)).setText(getString(R.string.connection_opening, getString(R.string.app_name)));
        ((TextView) connectionOverlay.findViewById(R.id.connection_message)).setText(R.string.connection_loading);
        connectionOverlay.findViewById(R.id.connection_progress).setVisibility(View.VISIBLE);
        connectionOverlay.findViewById(R.id.connection_retry).setVisibility(View.GONE);
        connectionTimeout = () -> showConnectionError(R.string.connection_timeout);
        connectionHandler.postDelayed(connectionTimeout, 30000);
        readinessProbe = () -> {
            if (isFinishing() || connectionFailed || generation != connectionGeneration) return;
            if (!documentCommitted) {
                connectionHandler.postDelayed(readinessProbe, 200);
                return;
            }
            // Wait for React to render the real interface, not just an empty HTML document.
            web.evaluateJavascript("(function(){var r=document.getElementById('root');return !!(r&&r.children.length&&r.getBoundingClientRect().height>0)})()", rendered -> {
                if (connectionFailed || generation != connectionGeneration || isFinishing()) return;
                if ("true".equals(rendered)) {
                    // Confirm the WebView compositor has a frame ready before uncovering it.
                    web.postVisualStateCallback(generation, new WebView.VisualStateCallback() {
                        @Override public void onComplete(long requestId) {
                            if (!connectionFailed && generation == connectionGeneration && !isFinishing()) {
                                cancelConnectionChecks();
                                connectionOverlay.setVisibility(View.GONE);
                            }
                        }
                    });
                } else connectionHandler.postDelayed(readinessProbe, 200);
            });
        };
        connectionHandler.post(readinessProbe);
    }

    private void showConnectionError(int message) {
        if (connectionOverlay == null || isFinishing()) return;
        connectionFailed = true;
        cancelConnectionChecks();
        connectionOverlay.setVisibility(View.VISIBLE);
        ((TextView) connectionOverlay.findViewById(R.id.connection_title)).setText(R.string.connection_failed_title);
        ((TextView) connectionOverlay.findViewById(R.id.connection_message)).setText(message);
        connectionOverlay.findViewById(R.id.connection_progress).setVisibility(View.GONE);
        connectionOverlay.findViewById(R.id.connection_retry).setVisibility(View.VISIBLE);
    }

    private void cancelConnectionChecks() {
        if (readinessProbe != null) connectionHandler.removeCallbacks(readinessProbe);
        if (connectionTimeout != null) connectionHandler.removeCallbacks(connectionTimeout);
    }

    @Override public void onDestroy() {
        connectionGeneration++;
        cancelConnectionChecks();
        super.onDestroy();
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
