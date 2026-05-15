<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\Cart;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'roles' => $request->user() ? $request->user()->getRoleNames()->values()->all() : [],
            ],
            'cartItemsCount' => $this->getCartItemsCount($request),
            'pendingApprovals' => $request->user() ? app(\App\Services\WorkflowService::class)->getPendingApprovalsForUser($request->user()) : [],
            'pendingRfqs' => $request->user() ? app(\App\Services\WorkflowService::class)->getPendingRfqsForUser($request->user()) : [],
            'appSettings' => \App\Models\AppSetting::first(),
            'flash' => [
                'type' => $request->session()->get('flash_type'),
                'payload' => $request->session()->get('flash_payload'),
                'message' => $request->session()->get('flash_message'),
                'return_url' => $request->session()->get('return_url'),
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'import_errors' => $request->session()->get('import_errors'),
            ],
        ];
    }

    private function getCartItemsCount(Request $request): int
    {
        $user = $request->user();
        if ($user) {
            $cart = Cart::where('user_id', $user->id)->first();
        } else {
            $cart = Cart::where('session_id', session()->getId())->first();
        }

        if (!$cart) {
            return 0;
        }

        return $cart->items()->sum('quantity');
    }
}
