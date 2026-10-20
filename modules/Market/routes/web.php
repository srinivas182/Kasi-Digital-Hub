<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Market\Http\Controllers\DeskController;
use Modules\Market\Http\Controllers\MarketController;
use Modules\Market\Http\Controllers\OrderController;
use Modules\Market\Http\Controllers\TraderController;
use Modules\Market\Http\Controllers\TrustController;

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
        // S26: refunds, payment settings, hub collection desk.
        Route::post('/shop/orders/{order}/refund', [OrderController::class, 'refund'])->middleware('throttle:20,60')->name('shop.order.refund');
        Route::post('/shop/bank', [TraderController::class, 'bank'])->middleware('throttle:10,60')->name('shop.bank');
        Route::get('/desk', [DeskController::class, 'index'])->name('desk');
        Route::post('/desk/orders/{order}/check-in', [DeskController::class, 'checkIn'])->name('desk.checkin');
        Route::post('/desk/orders/{order}/hand-over', [DeskController::class, 'handOver'])->middleware('throttle:30,10')->name('desk.handover');
        Route::post('/desk/settings', [DeskController::class, 'settings'])->name('desk.settings');

        // S27: trust and safety.
        Route::post('/orders/{order}/review', [TrustController::class, 'review'])->middleware('throttle:10,60')->name('orders.review');
        Route::post('/orders/{order}/dispute', [TrustController::class, 'openDispute'])->middleware('throttle:5,60')->name('orders.dispute');
        Route::post('/disputes/{dispute}/settle', [TrustController::class, 'settleDispute'])->name('disputes.settle');
        Route::post('/shop/reviews/{review}/reply', [TrustController::class, 'reply'])->whereNumber('review')->middleware('throttle:30,60')->name('shop.reviews.reply');
        Route::post('/shop/disputes/{dispute}/respond', [TrustController::class, 'respondDispute'])->middleware('throttle:10,60')->name('shop.disputes.respond');
        Route::post('/shop/orders/{order}/no-show', [TrustController::class, 'noShow'])->name('shop.order.noshow');
        Route::get('/shop/insights', [TrustController::class, 'insights'])->name('shop.insights');
        Route::get('/shop/insights.csv', [TrustController::class, 'csv'])->name('shop.insights.csv');
        Route::post('/shops/{slug}/report', [TrustController::class, 'reportShop'])->middleware('throttle:10,60')->name('shops.report');
        Route::get('/disputes/{dispute}', [TrustController::class, 'showDispute'])->middleware('permission:admin.support.handle')->name('disputes.show');
        Route::post('/disputes/{dispute}/decide', [TrustController::class, 'decideDispute'])->middleware(['permission:admin.support.handle', 'pin.confirmed'])->name('disputes.decide');
        Route::get('/moderate/{slug}', [TrustController::class, 'moderate'])->middleware('permission:admin.moderation.review')->name('moderate');
        Route::post('/moderate/{slug}', [TrustController::class, 'moderateAction'])->middleware(['permission:admin.moderation.review', 'pin.confirmed'])->name('moderate.action');
    });
});
