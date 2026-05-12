<?php

namespace App\Gateways;

use App\Contracts\PunchOutGatewayInterface;
use App\Models\Cart;
use Illuminate\Support\Facades\Session;

class AbetaGateway implements PunchOutGatewayInterface
{
    protected string $returnUrlKey = 'abeta_return_url';

    public function setupSession(array $payload): bool
    {
        // For a dummy driver, we expect a 'return_url' in the payload from Abeta.
        if (isset($payload['return_url'])) {
            Session::put($this->returnUrlKey, $payload['return_url']);
            return true;
        }

        return false;
    }

    public function buildReturnPayload(Cart $cart): array
    {
        $items = $cart->items->map(function ($item) {
            return [
                'sku' => $item->product->sku,
                'name' => $item->product->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->price,
            ];
        });

        // Abeta-specific structured JSON
        return [
            'status' => 'success',
            'order_ref' => 'CART-' . $cart->id,
            'total_amount' => $cart->items->sum(function ($item) {
                return $item->quantity * $item->price;
            }),
            'items' => $items->toArray(),
        ];
    }

    public function getReturnUrl(): string
    {
        return Session::get($this->returnUrlKey, url('/'));
    }
}
