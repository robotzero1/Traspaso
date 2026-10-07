<?php

namespace App\Payments;

/** The payment provider (Stripe), behind an interface so tests don't call it. */
interface PaymentGateway
{
    public function configured(): bool;

    /**
     * Opens a Checkout session.
     *
     * @param  array<string, mixed>  $params  Stripe Checkout Session parameters
     * @return array{id: string, url: string}
     */
    public function checkout(array $params): array;

    /** @return array{id: string, paid: bool} */
    public function session(string $id): array;

    /**
     * A verified webhook event; throws if the signature doesn't match.
     *
     * @return array{type: string, session_id: string|null, paid: bool}
     */
    public function event(string $payload, ?string $signature): array;
}
