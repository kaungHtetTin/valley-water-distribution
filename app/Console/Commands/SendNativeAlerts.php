<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\ActionAlertController;
use App\Models\NativeDevice;
use App\Services\FirebasePushService;
use App\Support\AppAccess;
use Illuminate\Console\Command;
use Throwable;

class SendNativeAlerts extends Command
{
    protected $signature = 'valley:push-alerts';

    protected $description = 'Send changed action queues to registered Android devices through Firebase.';

    public function handle(ActionAlertController $alerts, FirebasePushService $push): int
    {
        if (! is_file((string) config('native.firebase_credentials'))) {
            $this->error('Set FIREBASE_CREDENTIALS to the server-only Firebase service account JSON.');

            return self::FAILURE;
        }
        NativeDevice::where('expires_at', '<=', now())->delete();
        $failed = false;
        NativeDevice::with('user')->whereNotNull('fcm_token')->chunkById(100, function ($devices) use ($alerts, $push, &$failed) {
            foreach ($devices as $device) {
                if (! $device->user || ! AppAccess::canAccess($device->user, $device->app)) {
                    $device->delete();

                    continue;
                }
                try {
                    $state = [];
                    foreach ($alerts->forUser($device->user, $device->app)['items'] as $alert) {
                        $state[$alert['id']] = $alert['count'];
                        if (($device->alert_state[$alert['id']] ?? 0) !== $alert['count']) {
                            $push->send($device, $alert);
                        }
                    }
                    $device->update(['alert_state' => $state]);
                } catch (Throwable $exception) {
                    // Do not log tokens, private keys, or HTTP response/request bodies.
                    $failed = true;
                    $this->warn('Push failed for device '.$device->id.'. Check Firebase configuration and connectivity.');
                }
            }
        });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
