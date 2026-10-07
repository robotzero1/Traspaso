<?php

namespace App\Push;

use App\Models\PushSubscription;

/** Delivers a notification to one device. */
interface PushSender
{
    /**
     * @param  array{title: string, body: string, url: string, tag?: string}  $message
     * @return bool false when the device has unsubscribed (the subscription should be deleted)
     */
    public function send(PushSubscription $subscription, array $message): bool;
}
