<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Market\Http\Controllers\MarketController;
use Modules\Market\Http\Controllers\OrderController;
use Modules\Market\Http\Controllers\TraderController;

/* KasiMarket (S24). Public browsing on market.; the trader's pages need an account. */
Route::prefix('market')->name('market.')->group(function (): void {
    Route::get('/', [MarketController::class, 'home'])->name('home');
    Route::get('/search', [MarketController::class, 'search'])->middleware('throttle:120,1')->name('search');
    Route::get('/shops/{slug}', [MarketController::class, 'shop'])->where('slug', '[a-z0-9-]+')->name('shop.public');
    Route::get('/products/{product}', [MarketController::class, 'product'])->name('product');
    Route::post('/products/{product}/report', [MarketController::class, 'report'])->middleware(['auth', 'throttle:10,60'])->name('product.report');

    Route::middleware(['auth', 'account.ready'])->group(function (): void {
        Route::get('/shop', [TraderController::class, 'dashboard'])->name('shop');
        Route::post('/shop', [TraderController::class, 'open'])->middleware('throttle:5,60')->name('shop.open');
        Route::put('/shop', [TraderController::class, 'update'])->name('shop.update');
        Route::post('/shop/picture', [TraderController::class, 'picture'])->middleware('throttle:20,60')->name('shop.picture');
        Route::get('/shop/products/new', [TraderController::class, 'form'])->name('products.new');
        Route::post('/shop/products', [TraderController::class, 'save'])->middleware('throttle:60,60')->name('products.store');
        Route::get('/shop/products/{product}', [TraderController::class, 'form'])->name('products.edit');
        Route::post('/shop/products/{product}', [TraderController::class, 'save'])->middleware('throttle:60,60')->name('products.update');
        Route::post('/shop/products/{product}/publish', [TraderController::class, 'publish'])->middleware('throttle:60,60')->name('products.publish');
        Route::post('/shop/products/{product}/hide', [TraderController::class, 'hide'])->name('products.hide');
        Route::delete('/shop/products/{product}/photos/{photo}', [TraderController::class, 'removePhoto'])->name('products.photos.delete');

        // S25: cart and orders.
        Route::get('/cart', [OrderController::class, 'cart'])->name('cart');
        Route::post('/cart/{product}', [OrderController::class, 'add'])->middleware('throttle:60,1')->name('cart.add');
        Route::patch('/cart/items/{item}', [OrderController::class, 'setQuantity'])->whereNumber('item')->name('cart.set');
        Route::post('/cart/shops/{shop}/order', [OrderController::class, 'place'])->middleware('throttle:10,10')->name('orders.place');
        Route::get('/orders', [OrderController::class, 'mine'])->name('orders');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/orders/{order}/received', [OrderController::class, 'received'])->name('orders.received');
        Route::post('/orders/{order}/messages', [OrderController::class, 'message'])->middleware('throttle:30,10')->name('orders.message');
        Route::get('/shop/orders', [OrderController::class, 'traderOrders'])->name('shop.orders');
        Route::get('/shop/orders/{order}', [OrderController::class, 'show'])->name('shop.order');
        Route::post('/shop/orders/{order}/move', [OrderController::class, 'move'])->name('shop.order.move');
    });
});
