<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictSupplierCartAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supplierRoles = ['supplier_approver', 'supplier_processor', 'supplier_admin'];
        
        if (auth()->check() && auth()->user()->hasAnyRole($supplierRoles)) {
            abort(403, 'Access Denied: Supplier users cannot access or modify carts.');
        }

        return $next($request);
    }
}
