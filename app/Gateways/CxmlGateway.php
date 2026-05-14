<?php

namespace App\Gateways;

use App\Contracts\PunchOutGatewayInterface;
use App\Models\Cart;
use Illuminate\Support\Facades\Session;

class CxmlGateway implements PunchOutGatewayInterface
{
    protected string $returnUrlKey = 'cxml_return_url';

    public function setupSession(array $payload): bool
    {
        // cXML setup request usually contains a browser_form_post address
        $browserFormPost = $payload['browser_form_post'] ?? null;
        if ($browserFormPost) {
            Session::put($this->returnUrlKey, $browserFormPost);
            return true;
        }
        return false;
    }

    public function buildReturnPayload(Cart $cart): array
    {
        $timestamp = date('Y-m-d\TH:i:sP');
        $totalAmount = number_format($cart->items->sum(fn($i) => $i->quantity * $i->price), 2, '.', '');

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<!DOCTYPE cXML SYSTEM \"http://xml.cxml.org/schemas/cXML/1.2.014/cXML.dtd\">\n";
        $xml .= "<cXML payloadID=\"{$cart->id}-{$timestamp}\" timestamp=\"{$timestamp}\">\n";
        $xml .= "  <Header>\n";
        $xml .= "    <From><Credential domain=\"NetworkId\"><Identity>EProcurement</Identity></Credential></From>\n";
        $xml .= "    <To><Credential domain=\"NetworkId\"><Identity>BuyerSystem</Identity></Credential></To>\n";
        $xml .= "    <Sender><Credential domain=\"NetworkId\"><Identity>EProcurement</Identity></Credential><UserAgent>EProcApp</UserAgent></Sender>\n";
        $xml .= "  </Header>\n";
        $xml .= "  <Message>\n";
        $xml .= "    <PunchOutOrderMessage>\n";
        $xml .= "      <BuyerCookie>COOKIE-" . $cart->id . "</BuyerCookie>\n";
        $xml .= "      <PunchOutOrderMessageHeader operationAllowed=\"create\">\n";
        $xml .= "        <Total><Money currency=\"MYR\">{$totalAmount}</Money></Total>\n";
        $xml .= "      </PunchOutOrderMessageHeader>\n";

        foreach ($cart->items as $index => $item) {
            $lineNum = $index + 1;
            $price = number_format($item->price, 2, '.', '');
            $xml .= "      <ItemIn quantity=\"{$item->quantity}\" lineNumber=\"{$lineNum}\">\n";
            $xml .= "        <ItemID>\n";
            $xml .= "          <SupplierPartID>{$item->product->sku}</SupplierPartID>\n";
            $xml .= "        </ItemID>\n";
            $xml .= "        <ItemDetail>\n";
            $xml .= "          <UnitPrice><Money currency=\"MYR\">{$price}</Money></UnitPrice>\n";
            $xml .= "          <Description xml:lang=\"en\">" . htmlspecialchars($item->product->name) . "</Description>\n";
            $xml .= "          <UnitOfMeasure>EA</UnitOfMeasure>\n";
            $xml .= "          <Classification domain=\"UNSPSC\">00000000</Classification>\n";
            $xml .= "        </ItemDetail>\n";
            $xml .= "      </ItemIn>\n";
        }

        $xml .= "    </PunchOutOrderMessage>\n";
        $xml .= "  </Message>\n";
        $xml .= "</cXML>\n";

        return [
            'cxml-urlencoded' => $xml
        ];
    }

    public function getReturnUrl(): string
    {
        return Session::get($this->returnUrlKey, url('/'));
    }
}
