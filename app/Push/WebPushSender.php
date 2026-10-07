<?php

namespace App\Push;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Web Push with VAPID keys from config/webpush.php. Without keys
 * (webpush:vapid hasn't been run) nothing is sent.
 */
final class WebPushSender implements PushSender
{
    private ?WebPush $webPush = null;

    public function send(PushSubscription $subscription, array $message): bool
    {
        $webPush = $this->webPush();

        if ($webPush === null) {
            return true;
        }

        $report = $webPush->sendOneNotification(
            Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => $subscription->content_encoding,
            ]),
            json_encode($message, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ['TTL' => 12 * 3600, 'urgency' => 'normal', 'topic' => substr(preg_replace('/[^A-Za-z0-9_-]/', '', $message['tag'] ?? 'traspaso'), 0, 32)],
        );

        return ! $report->isSubscriptionExpired();
    }

    private function webPush(): ?WebPush
    {
        $keys = config('webpush');

        if (blank($keys['public_key']) || blank($keys['private_key'])) {
            return null;
        }

        return $this->webPush ??= new WebPush(['VAPID' => [
            'subject' => $keys['subject'],
            'publicKey' => $keys['public_key'],
            'privateKey' => $keys['private_key'],
        ]]);
    }
}
