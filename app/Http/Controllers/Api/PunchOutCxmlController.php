<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PunchOutCxmlController extends Controller
{
    /**
     * Handle incoming inbound cXML OrderRequest (Purchase Order webhook).
     */
    public function handleOrder(Request $request)
    {
        $content = $request->getContent();

        // Capture raw content or standard URL-encoded format variations
        if ($request->has('cxml-urlencoded')) {
            $content = urldecode($request->input('cxml-urlencoded'));
        } elseif (empty($content) && $request->has('body')) {
            $content = $request->input('body');
        }

        Log::info("Inbound cXML OrderRequest received by ERP adapter.", ['length' => strlen($content)]);

        if (empty($content)) {
            return $this->cxmlErrorResponse('400', 'Bad Request', 'Payload content is empty');
        }

        try {
            // Parse XML safely ensuring CDATA handles seamlessly
            $xml = @simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml === false) {
                return $this->cxmlErrorResponse('400', 'Bad Request', 'Invalid XML formatting');
            }

            // 1. Parse Enterprise Identity Credentials
            $senderIdentity = (string) ($xml->Header->Sender->Credential->Identity ?? '');
            $sharedSecret = (string) ($xml->Header->Sender->Credential->SharedSecret ?? '');
            $fromIdentity = (string) ($xml->Header->From->Credential->Identity ?? '');

            // Lookup identity using Sender first, fallback to From identity
            $lookupIdentity = !empty($senderIdentity) ? $senderIdentity : $fromIdentity;

            $company = null;
            if (!empty($lookupIdentity)) {
                $company = Company::where('punchout_enabled', true)
                    ->where('punchout_identity', $lookupIdentity)
                    ->where('punchout_shared_secret', $sharedSecret)
                    ->first();
            }

            // Enforce rigorous validation to prevent unsanctioned incoming orders
            if (!$company) {
                Log::warning("cXML auth failed: No registered company matches provided credentials.", ['identity' => $lookupIdentity]);
                return $this->cxmlErrorResponse('401', 'Unauthorized', 'Invalid PunchOut Identity or Shared Secret');
            }

            // 2. Assign Order to local tenancy holder (first company user)
            $user = User::where('company_id', $company->id)->first();
            if (!$user) {
                // Fallback to first available system administrator for logging
                $user = User::role('admin')->first();
            }

            // 3. Walk cXML elements -> Request -> OrderRequest
            $orderRequest = $xml->Request->OrderRequest;
            if (!$orderRequest) {
                return $this->cxmlErrorResponse('400', 'Bad Request', 'cXML payload contains no OrderRequest element');
            }

            $orderId = (string) ($orderRequest->OrderRequestHeader['orderID'] ?? '');
            $poDate = (string) ($orderRequest->OrderRequestHeader['orderDate'] ?? '');
            $deploymentMode = (string) ($xml->Request['deploymentMode'] ?? 'production');
            $currency = (string) ($orderRequest->OrderRequestHeader->Total->Money['currency'] ?? 'USD');
            $totalAmount = (float) ($orderRequest->OrderRequestHeader->Total->Money ?? 0);

            // Parse nested structured addresses
            $shippingInfo = $this->parseAddress($orderRequest->OrderRequestHeader->ShipTo->Address ?? null);
            $billingInfo = $this->parseAddress($orderRequest->OrderRequestHeader->BillTo->Address ?? null);

            // Start explicit transactional block
            DB::beginTransaction();

            // Persist Order object with full cXML header context
            $order = Order::create([
                'company_id' => $company->id,
                'user_id' => $user ? $user->id : null,
                'status' => 'approved', // Coming directly from procurement ERP, treat as approved
                'punchout_po_reference' => $orderId,
                'po_date' => $poDate,
                'deployment_mode' => $deploymentMode,
                'currency' => substr(strtoupper($currency), 0, 3),
                'total' => $totalAmount,
                
                // Add fully extracted shipping details
                'shipping_name' => $shippingInfo['name'],
                'shipping_email' => $shippingInfo['email'],
                'shipping_address' => $shippingInfo['address'],
                
                // Add fully extracted billing details
                'billing_name' => $billingInfo['name'],
                'billing_email' => $billingInfo['email'],
                'billing_address' => $billingInfo['address'],
            ]);

            // Iterate individual LineItems
            $actualTotal = 0;
            foreach ($orderRequest->ItemOut as $itemOut) {
                $sku = (string) ($itemOut->ItemID->SupplierPartID ?? '');
                $qty = (int) ($itemOut['quantity'] ?? 1);
                
                // Extract B2B specialized item details
                $detail = $itemOut->ItemDetail;
                $price = (float) ($detail->UnitPrice->Money ?? 0);
                $uom = (string) ($detail->UnitOfMeasure ?? null);
                $classification = (string) ($detail->Classification ?? null);
                $mfgPartId = (string) ($detail->ManufacturerPartID ?? null);
                $mfgName = (string) ($detail->ManufacturerName ?? null);

                $product = Product::where('sku', $sku)->first();

                // Synchronize inbound metadata catalog properties with Product storage if found
                if ($product) {
                    $productUpdates = array_filter([
                        'uom' => $uom ?: null,
                        'classification' => $classification ?: null,
                        'manufacturer_part_id' => $mfgPartId ?: null,
                        'manufacturer_name' => $mfgName ?: null,
                    ]);
                    
                    if (!empty($productUpdates)) {
                        $product->update($productUpdates);
                    }
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product ? $product->id : null,
                    'quantity' => $qty,
                    'price' => $price,
                ]);

                $actualTotal += ($qty * $price);
            }

            // Re-concile totals to guarantee ledger consistency
            if ($actualTotal > 0 && abs($actualTotal - $totalAmount) > 0.01) {
                $order->update(['total' => $actualTotal]);
            }

            DB::commit();

            Log::info("cXML Order parsed and stored successfully.", ['order_id' => $order->id, 'po_ref' => $orderId]);

            return $this->cxmlSuccessResponse('Successfully received Purchase Order ' . $orderId . ' with ID ' . $order->id);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Fatal exception caught parsing inbound cXML payload.", [
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
            return $this->cxmlErrorResponse('500', 'Internal Error', $e->getMessage());
        }
    }

    /**
     * Helper to robustly compile standard cXML nested Address constructs.
     */
    protected function parseAddress($addressNode)
    {
        if (!$addressNode) {
            return ['name' => null, 'email' => null, 'address' => null];
        }

        $name = (string) ($addressNode->Name ?? '');
        $email = (string) ($addressNode->Email ?? '');
        
        $lines = [];
        if (isset($addressNode->PostalAddress)) {
            $pa = $addressNode->PostalAddress;
            
            // Some standard cXML formats include DeliverTo inside PostalAddress
            if (!empty((string)($pa->DeliverTo ?? ''))) {
                $lines[] = 'Deliver To: ' . (string) $pa->DeliverTo;
            }
            
            if (!empty((string)($pa->Street ?? ''))) {
                $lines[] = (string) $pa->Street;
            }
            
            $cityLine = [];
            if (!empty((string)($pa->City ?? ''))) {
                $cityLine[] = (string) $pa->City;
            }
            if (!empty((string)($pa->State ?? ''))) {
                $cityLine[] = (string) $pa->State;
            }
            if (!empty((string)($pa->PostalCode ?? ''))) {
                $cityLine[] = (string) $pa->PostalCode;
            }
            
            if (count($cityLine) > 0) {
                $lines[] = implode(', ', $cityLine);
            }
            
            if (!empty((string)($pa->Country ?? ''))) {
                $iso = (string) ($pa->Country['isoCountryCode'] ?? '');
                $lines[] = (string) $pa->Country . (!empty($iso) ? " ({$iso})" : '');
            }
        }

        return [
            'name' => !empty($name) ? trim($name) : null,
            'email' => !empty($email) ? trim($email) : null,
            'address' => count($lines) > 0 ? implode("\n", $lines) : null,
        ];
    }

    /**
     * Generate standard success cXML structure.
     */
    protected function cxmlSuccessResponse(string $message)
    {
        $timestamp = date('Y-m-d\TH:i:sP');
        $payloadId = 'response-' . uniqid() . '@eprocurement';

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE cXML SYSTEM "http://xml.cxml.org/schemas/cXML/1.2.014/cXML.dtd">
<cXML payloadID="{$payloadId}" timestamp="{$timestamp}">
  <Response>
    <Status code="200" text="OK">{$message}</Status>
  </Response>
</cXML>
XML;

        return response($xml, 200)->header('Content-Type', 'text/xml');
    }

    /**
     * Generate standard failure cXML structure.
     */
    protected function cxmlErrorResponse(string $code, string $text, string $message)
    {
        $timestamp = date('Y-m-d\TH:i:sP');
        $payloadId = 'error-' . uniqid() . '@eprocurement';

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE cXML SYSTEM "http://xml.cxml.org/schemas/cXML/1.2.014/cXML.dtd">
<cXML payloadID="{$payloadId}" timestamp="{$timestamp}">
  <Response>
    <Status code="{$code}" text="{$text}">{$message}</Status>
  </Response>
</cXML>
XML;

        return response($xml, 200)->header('Content-Type', 'text/xml');
    }
}
