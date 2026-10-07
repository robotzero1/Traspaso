<?php

namespace App\Payments;

use Stripe\StripeClient;
use Stripe\Webhook;

final class StripeGateway implements PaymentGateway
{
    public function configured(): bool
    {
        return filled(config('payments.stripe.secret'));
    }

    public function checkout(array $params): array
    {
        $session = $this->client()->checkout->sessions->create($params);

        return ['id' => $session->id, 'url' => $session->url];
    }

    public function session(string $id): array
    {
        $session = $this->client()->checkout->sessions->retrieve($id);

        return ['id' => $session->id, 'paid' => $session->payment_status === 'paid'];
    }

    public function event(string $payload, ?string $signature): array
    {
        $event = Webhook::constructEvent($payload, (string) $signature, (string) config('payments.stripe.webhook_secret'));
        $object = $event->data->object;

        return [
            'type' => $event->type,
            'session_id' => $object->object === 'checkout.session' ? $object->id : null,
            'paid' => ($object->payment_status ?? null) === 'paid',
        ];
    }

    private function client(): StripeClient
    {
        return new StripeClient((string) config('payments.stripe.secret'));
    }
}
