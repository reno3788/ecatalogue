<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ClientPriceListController;
use App\Http\Controllers\Admin\AppSettingController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\OrderController;

Route::get('/', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/products/{product}', [CatalogController::class, 'show'])->name('catalog.show');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
Route::delete('/cart/item/{cartItem}', [CartController::class, 'remove'])->name('cart.remove');

Route::middleware('auth')->group(function () {
    Route::post('/checkout', [CheckoutController::class, 'process'])->name('checkout.process');
    
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
});

Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin Routes
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    // Must come BEFORE resource() so 'template'/'upload' are not treated as product IDs
    Route::get('/products/template', [ProductController::class, 'downloadTemplate'])->name('products.template');
    Route::post('/products/upload', [ProductController::class, 'uploadBulk'])->name('products.upload');
    Route::resource('products', ProductController::class);
    Route::put('categories/{category}/update-parent', [CategoryController::class, 'updateParent'])->name('categories.update-parent');
    Route::resource('categories', CategoryController::class);
    Route::resource('users', UserController::class);
    Route::get('/app-settings', [AppSettingController::class, 'index'])->name('app-settings.index');
    Route::post('/app-settings', [AppSettingController::class, 'update'])->name('app-settings.update');
    Route::post('/app-settings/test-smtp', [AppSettingController::class, 'testSmtp'])->name('app-settings.test-smtp');
});

// Admin/Supplier Admin Routes
Route::middleware(['auth', 'role:admin,supplier_processor,supplier_approver'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('client-price-lists/template', [ClientPriceListController::class, 'downloadTemplate'])->name('client-price-lists.template');
    Route::post('client-price-lists/upload', [ClientPriceListController::class, 'upload'])->name('client-price-lists.upload');
    Route::resource('client-price-lists', ClientPriceListController::class);
    
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::post('/orders/batch-status', [AdminOrderController::class, 'batchUpdateStatus'])->name('orders.batch-update-status');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.update-status');
});

require __DIR__.'/auth.php';
