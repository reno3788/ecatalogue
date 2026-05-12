<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\DynamicPricingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CartController extends Controller
{
    protected DynamicPricingService $pricingService;

    public function __construct(DynamicPricingService $pricingService)
    {
        $this->pricingService = $pricingService;
    }

    public function index()
    {
        $user = auth()->user();
        
        $query = Cart::with('items.product.images');
        if ($user) {
            $cart = $query->firstOrCreate(['user_id' => $user->id]);
        } else {
            $cart = $query->firstOrCreate(['session_id' => session()->getId()]);
        }

        return Inertia::render('Cart/Index', [
            'cart' => $cart
        ]);
    }

    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = auth()->user();
        $product = Product::findOrFail($request->product_id);

        // Resolve Price
        $price = $product->base_price;
        if ($user && $user->company_id) {
            $price = $this->pricingService->resolvePrice($product->id, $user->company_id) ?? $product->base_price;
        }

        if ($user) {
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        } else {
            $cart = Cart::firstOrCreate(['session_id' => session()->getId()]);
        }

        $cartItem = CartItem::where('cart_id', $cart->id)
                            ->where('product_id', $product->id)
                            ->first();

        if ($cartItem) {
            $cartItem->quantity += $request->quantity;
            $cartItem->price = $price; // Update to latest price
            $cartItem->save();
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => $request->quantity,
                'price' => $price,
            ]);
        }

        return redirect()->back()->with('success', 'Product added to cart!');
    }

    public function remove(CartItem $cartItem)
    {
        $user = auth()->user();
        if ($user && $cartItem->cart->user_id !== $user->id) {
            abort(403);
        } else if (!$user && $cartItem->cart->session_id !== session()->getId()) {
            abort(403);
        }

        $cartItem->delete();

        return redirect()->back()->with('success', 'Product removed from cart!');
    }
}
