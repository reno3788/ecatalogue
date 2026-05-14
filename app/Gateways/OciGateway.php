<?php

namespace App\Gateways;

use App\Contracts\PunchOutGatewayInterface;
use App\Models\Cart;
use Illuminate\Support\Facades\Session;

class OciGateway implements PunchOutGatewayInterface
{
    protected string $returnUrlKey = 'oci_return_url';

    public function setupSession(array $payload): bool
    {
        // OCI standard POST parameter for the return target is hook_url or HOOK_URL
        $hookUrl = $payload['hook_url'] ?? $payload['HOOK_URL'] ?? null;
        if ($hookUrl) {
            Session::put($this->returnUrlKey, $hookUrl);
            return true;
        }
        return false;
    }

    public function buildReturnPayload(Cart $cart): array
    {
        // OCI uses flat HTML form-post fieldlists with indexed suffix
        $payload = [];
        $index = 1;

        foreach ($cart->items as $item) {
            $payload["NEW_ITEM-DESCRIPTION[{$index}]"] = $item->product->name;
            $payload["NEW_ITEM-MATNR[{$index}]"] = $item->product->sku;
            $payload["NEW_ITEM-QUANTITY[{$index}]"] = $item->quantity;
            $payload["NEW_ITEM-UNIT[{$index}]"] = 'EA';
            $payload["NEW_ITEM-PRICE[{$index}]"] = number_format($item->price, 2, '.', '');
            $payload["NEW_ITEM-CURRENCY[{$index}]"] = 'MYR';
            $payload["NEW_ITEM-VENDORMAT[{$index}]"] = $item->product->sku;
            $index++;
        }

        return $payload;
    }

    public function getReturnUrl(): string
    {
        return Session::get($this->returnUrlKey, url('/'));
    }
}
