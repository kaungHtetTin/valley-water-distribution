<?php

namespace App\Services;

use App\Models\NativeDevice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FirebasePushService
{
    public function send(NativeDevice $device, array $alert): void
    {
        $credentials = json_decode(file_get_contents(config('native.firebase_credentials')), true, flags: JSON_THROW_ON_ERROR);
        $project = $credentials['project_id'] ?? '';
        if (! preg_match('/^[a-z0-9-]+$/', $project)) {
            throw new RuntimeException('Invalid Firebase project configuration.');
        }
        $accessToken = Cache::remember('native-fcm:'.hash('sha256', $credentials['client_email'].$credentials['private_key_id']), 3000, function () use ($credentials) {
            $encode = fn ($value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
            $unsigned = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$encode(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => time(), 'exp' => time() + 3600,
            ]));
            if (! openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Unable to sign Firebase credentials.');
            }
            $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned.'.'.$encode($signature),
            ]);
            if (! $response->successful() || ! is_string($response->json('access_token'))) {
                throw new RuntimeException('Firebase authentication failed (HTTP '.$response->status().').');
            }

            return $response->json('access_token');
        });
        $response = Http::withToken($accessToken)->timeout(20)->post("https://fcm.googleapis.com/v1/projects/{$project}/messages:send", [
            'message' => [
                'token' => $device->fcm_token,
                'notification' => [
                    'title' => 'Valley '.ucfirst($device->app),
                    'body' => $alert['count'].' '.$alert['label'].'. Open the app to review.',
                ],
                'data' => ['app' => $device->app, 'path' => $alert['path']],
                'android' => ['priority' => 'high', 'ttl' => '3600s', 'notification' => [
                    'channel_id' => 'valley_alerts', 'tag' => $alert['id'], 'visibility' => 'PRIVATE',
                ]],
            ],
        ]);
        if (collect($response->json('error.details', []))->contains(fn ($detail) => ($detail['errorCode'] ?? '') === 'UNREGISTERED')) {
            $device->update(['fcm_token' => null]);

            return;
        }
        if (! $response->successful()) {
            throw new RuntimeException('Firebase delivery failed (HTTP '.$response->status().').');
        }
    }
}
