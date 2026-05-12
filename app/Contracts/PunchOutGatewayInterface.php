<?php

namespace App\Contracts;

use App\Models\Cart;

interface PunchOutGatewayInterface
{
    /**
     * Initializes a PunchOut session with an incoming payload.
     */
    public function setupSession(array $payload): bool;

    /**
     * Translates our Cart into the specific gateway's required format.
     */
    public function buildReturnPayload(Cart $cart): array;

    /**
     * Retrieves the URL to send the user back to their eProcurement system.
     */
    public function getReturnUrl(): string;
}
