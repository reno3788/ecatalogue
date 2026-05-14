<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Services\PunchOutGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    protected PunchOutGatewayManager $gatewayManager;

    public function __construct(PunchOutGatewayManager $gatewayManager)
    {
        $this->gatewayManager = $gatewayManager;
    }

    /**
     * Handles the checkout process.
     */
    public function process(Request $request)
    {
        $user = auth()->user();
        
        // Find user's active cart
        $cart = Cart::with('items.product')->where('user_id', $user->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['error' => 'Cart is empty'], 400);
        }

        if ($user->is_punchout_user) {
            // PunchOut Flow: Load user's company and resolve configured gateway
            $company = $user->company;
            $gatewayName = ($company && $company->punchout_enabled) ? $company->punchout_gateway : 'abeta';
            $gateway = $this->gatewayManager->resolve($gatewayName ?: 'abeta');
            
            $payload = $gateway->buildReturnPayload($cart);
            $returnUrl = $gateway->getReturnUrl();

            return redirect()->back()->with([
                'flash_type' => 'punchout_return',
                'flash_payload' => $payload,
                'flash_message' => 'PunchOut complete',
                'return_url' => $returnUrl,
            ]);
        } else {
            // Direct Order Flow
            return DB::transaction(function () use ($user, $cart) {
                // Create Order
                $order = Order::create([
                    'company_id' => $user->company_id,
                    'user_id' => $user->id,
                    'status' => 'RFQ',
                    'total' => 0,
                ]);

                $total = 0;
                // Transfer Cart Items to Order Items
                foreach ($cart->items as $item) {
                    $order->items()->create([
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'price' => $item->price, // Storing frozen price at time of order
                    ]);
                    $total += $item->price * $item->quantity;
                }

                // Update Order Total
                $order->update(['total' => $total]);

                // Clear the cart
                $cart->items()->delete();
                $cart->delete();

                return redirect()->back()->with([
                    'flash_type' => 'direct_order',
                    'flash_message' => 'RFQ generated successfully',
                ]);
            });
        }
    }
}
