<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CartController;
use App\Http\Middleware\RestrictSupplierCartAccess;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ClientPriceListController;
use App\Http\Controllers\Admin\AppSettingController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\WorkflowController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CompanyController;

Route::get('/', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/products/{product}', [CatalogController::class, 'show'])->name('catalog.show');

Route::middleware([RestrictSupplierCartAccess::class])->group(function () {
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
    Route::patch('/cart/item/{cartItem}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/item/{cartItem}', [CartController::class, 'remove'])->name('cart.remove');
});

Route::middleware('auth')->group(function () {
    Route::post('/checkout', [CheckoutController::class, 'process'])->name('checkout.process');
    
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
    Route::post('/orders/{order}/negotiation/bargain', [App\Http\Controllers\OrderNegotiationController::class, 'requestBetterPrice'])->name('orders.negotiation.bargain');
    Route::post('/orders/{order}/negotiation/accept', [App\Http\Controllers\OrderNegotiationController::class, 'acceptOffer'])->name('orders.negotiation.accept');
});

Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

use App\Http\Controllers\NotificationController;

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Notifications
    Route::get('/api/notifications/unread', [NotificationController::class, 'getUnread'])->name('notifications.unread');
    Route::post('/api/notifications/{id}/mark-read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
});

use App\Http\Controllers\Admin\CarrierController;

// Admin Strictly Protected Routes
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', UserController::class);
    Route::resource('workflows', WorkflowController::class);
    Route::get('/app-settings', [AppSettingController::class, 'index'])->name('app-settings.index');
    Route::post('/app-settings', [AppSettingController::class, 'update'])->name('app-settings.update');
    Route::post('/app-settings/test-smtp', [AppSettingController::class, 'testSmtp'])->name('app-settings.test-smtp');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::resource('carriers', CarrierController::class)->except(['create', 'edit', 'show']);
});

// Shared Admin / Supplier Management Routes
Route::middleware(['auth', 'role:admin,supplier_admin,supplier_processor,supplier_approver'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Products (Shared)
    Route::get('/products/template', [ProductController::class, 'downloadTemplate'])->name('products.template');
    Route::post('/products/upload', [ProductController::class, 'uploadBulk'])->name('products.upload');
    Route::resource('products', ProductController::class);
    
    // Categories (Shared)
    Route::put('categories/{category}/update-parent', [CategoryController::class, 'updateParent'])->name('categories.update-parent');
    Route::resource('categories', CategoryController::class);
    
    // Client Price Lists
    Route::get('client-price-lists/template', [ClientPriceListController::class, 'downloadTemplate'])->name('client-price-lists.template');
    Route::post('client-price-lists/upload', [ClientPriceListController::class, 'upload'])->name('client-price-lists.upload');
    Route::resource('client-price-lists', ClientPriceListController::class);

    // Companies (Shared view, admin mutate)
    Route::resource('companies', CompanyController::class);

    
    // Orders
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::post('/orders/batch-status', [AdminOrderController::class, 'batchUpdateStatus'])->name('orders.batch-update-status');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.update-status');
    Route::get('/orders/{order}/shipments/create', [AdminOrderController::class, 'createShipmentForm'])->name('orders.create-shipment-form');
    Route::post('/orders/{order}/shipments', [AdminOrderController::class, 'createShipment'])->name('orders.create-shipment');
    Route::post('/orders/{order}/invoice', [AdminOrderController::class, 'uploadInvoice'])->name('orders.upload-invoice');
    Route::post('/orders/{order}/negotiation/offer', [App\Http\Controllers\OrderNegotiationController::class, 'submitOffer'])->name('orders.negotiation.offer');
});


require __DIR__.'/auth.php';
