<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0',
        'environment' => app()->environment(),
    ]);
});

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [App\Http\Controllers\Auth\RegisterController::class, 'register']);
    Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'login']);
    Route::post('/forgot-password', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'sendResetLinkEmail']);
    Route::post('/reset-password', [App\Http\Controllers\Auth\ResetPasswordController::class, 'reset']);
    Route::get('/verify-email/{id}/{hash}', [App\Http\Controllers\Auth\RegisterController::class, 'verifyEmail'])
        ->middleware(['signed'])->name('verification.verify');
    Route::post('/validate-reset-token', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'validateResetToken']);
});

// Public product routes
Route::prefix('products')->group(function () {
    Route::get('/', [App\Http\Controllers\ProductController::class, 'index']);
    Route::get('/{product}', [App\Http\Controllers\ProductController::class, 'show']);
    Route::post('/search', [App\Http\Controllers\ProductController::class, 'search']);
});

Route::get('/categories', [App\Http\Controllers\CategoryController::class, 'index']);
Route::get('/categories/{category}', [App\Http\Controllers\CategoryController::class, 'show']);
Route::get('/brands', [App\Http\Controllers\BrandController::class, 'index']);
Route::get('/brands/{brand}', [App\Http\Controllers\BrandController::class, 'show']);
Route::get('/filters', [App\Http\Controllers\FilterController::class, 'index']);

// Payment webhook routes (public, no auth required)
Route::prefix('webhooks')->group(function () {
    Route::post('/gcash', [App\Http\Controllers\PaymentController::class, 'handleGCashWebhook'])->name('payments.webhook.gcash');
    Route::post('/maya', [App\Http\Controllers\PaymentController::class, 'handleMayaWebhook'])->name('payments.webhook.maya');
    Route::post('/bank-transfer', [App\Http\Controllers\PaymentController::class, 'handleBankTransferWebhook'])->name('payments.webhook.bank_transfer');
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // User routes
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [App\Http\Controllers\Auth\LoginController::class, 'logout']);
        Route::post('/logout-all', [App\Http\Controllers\Auth\LoginController::class, 'logoutAll']);
        Route::post('/refresh', [App\Http\Controllers\Auth\LoginController::class, 'refresh']);
        Route::get('/me', [App\Http\Controllers\Auth\LoginController::class, 'me']);
        Route::post('/change-password', [App\Http\Controllers\Auth\ResetPasswordController::class, 'changePassword']);
        Route::post('/resend-verification', [App\Http\Controllers\Auth\RegisterController::class, 'resendVerification']);
    });

    // Cart routes
    Route::prefix('cart')->group(function () {
        Route::get('/', [App\Http\Controllers\CartController::class, 'index']);
        Route::post('/items', [App\Http\Controllers\CartController::class, 'addItem']);
        Route::put('/items/{item}', [App\Http\Controllers\CartController::class, 'updateItem']);
        Route::delete('/items/{item}', [App\Http\Controllers\CartController::class, 'removeItem']);
        Route::delete('/clear', [App\Http\Controllers\CartController::class, 'clear']);
    });

    // Order routes
    Route::prefix('orders')->group(function () {
        Route::get('/', [App\Http\Controllers\OrderController::class, 'index']);
        Route::post('/', [App\Http\Controllers\OrderController::class, 'store']);
        Route::get('/{order}', [App\Http\Controllers\OrderController::class, 'show']);
        Route::put('/{order}/cancel', [App\Http\Controllers\OrderController::class, 'cancel']);
    });

    // Pre-order routes
    Route::prefix('preorders')->group(function () {
        Route::get('/', [App\Http\Controllers\PreOrderController::class, 'index']);
        Route::post('/', [App\Http\Controllers\PreOrderController::class, 'store']);
        Route::get('/{preorder}', [App\Http\Controllers\PreOrderController::class, 'show']);
        Route::post('/{preorder}/deposit', [App\Http\Controllers\PreOrderController::class, 'payDeposit']);
        Route::post('/{preorder}/complete-payment', [App\Http\Controllers\PreOrderController::class, 'completePayment']);
        Route::get('/{preorder}/status', [App\Http\Controllers\PreOrderController::class, 'status']);
        Route::get('/{preorder}/notifications', [App\Http\Controllers\PreOrderController::class, 'notifications']);
    });

    // Loyalty routes
    Route::prefix('loyalty')->group(function () {
        Route::get('/balance', [App\Http\Controllers\LoyaltyController::class, 'balance']);
        Route::get('/transactions', [App\Http\Controllers\LoyaltyController::class, 'transactions']);
        Route::post('/redeem', [App\Http\Controllers\LoyaltyController::class, 'redeem']);
        Route::get('/tier-status', [App\Http\Controllers\LoyaltyController::class, 'tierStatus']);
        Route::post('/calculate-earnings', [App\Http\Controllers\LoyaltyController::class, 'calculateEarnings']);
        Route::post('/earn-credits', [App\Http\Controllers\LoyaltyController::class, 'earnCredits']);
        Route::get('/expiring-credits', [App\Http\Controllers\LoyaltyController::class, 'expiringCredits']);
    });

    // Payment routes
    Route::prefix('payments')->group(function () {
        Route::get('/methods', [App\Http\Controllers\PaymentController::class, 'getPaymentMethods']);
        Route::post('/gcash', [App\Http\Controllers\PaymentController::class, 'processGCash']);
        Route::post('/maya', [App\Http\Controllers\PaymentController::class, 'processMaya']);
        Route::post('/bank-transfer', [App\Http\Controllers\PaymentController::class, 'processBankTransfer']);
        Route::get('/{payment}/status', [App\Http\Controllers\PaymentController::class, 'status']);
        Route::post('/{payment}/verify', [App\Http\Controllers\PaymentController::class, 'verify']);
        Route::post('/{payment}/refund', [App\Http\Controllers\PaymentController::class, 'refund']);
    });

    // User profile routes
    Route::prefix('profile')->group(function () {
        Route::get('/', [App\Http\Controllers\ProfileController::class, 'show']);
        Route::put('/', [App\Http\Controllers\ProfileController::class, 'update']);
        Route::post('/avatar', [App\Http\Controllers\ProfileController::class, 'uploadAvatar']);
        Route::delete('/avatar', [App\Http\Controllers\ProfileController::class, 'deleteAvatar']);
    });

    // Wishlist routes
    Route::prefix('wishlist')->group(function () {
        Route::get('/', [App\Http\Controllers\WishlistController::class, 'index']);
        Route::post('/items', [App\Http\Controllers\WishlistController::class, 'addItem']);
        Route::delete('/items/{product}', [App\Http\Controllers\WishlistController::class, 'removeItem']);
        Route::delete('/clear', [App\Http\Controllers\WishlistController::class, 'clear']);
    });
});

// Admin routes
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    // Dashboard routes
    Route::get('/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index']);
    Route::get('/analytics', [App\Http\Controllers\Admin\AnalyticsController::class, 'index']);

    // Product management
    Route::apiResource('products', App\Http\Controllers\Admin\ProductController::class);
    Route::apiResource('categories', App\Http\Controllers\Admin\CategoryController::class);
    Route::apiResource('brands', App\Http\Controllers\Admin\BrandController::class);

    // Order management
    Route::prefix('orders')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\OrderController::class, 'index']);
        Route::get('/{order}', [App\Http\Controllers\Admin\OrderController::class, 'show']);
        Route::put('/{order}/status', [App\Http\Controllers\Admin\OrderController::class, 'updateStatus']);
        Route::post('/bulk-update', [App\Http\Controllers\Admin\OrderController::class, 'bulkUpdate']);
    });

    // User management
    Route::apiResource('users', App\Http\Controllers\Admin\UserController::class);

    // Inventory management
    Route::prefix('inventory')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\InventoryController::class, 'index']);
        Route::put('/{product}/stock', [App\Http\Controllers\Admin\InventoryController::class, 'updateStock']);
        Route::get('/low-stock', [App\Http\Controllers\Admin\InventoryController::class, 'lowStock']);
        Route::get('/reports', [App\Http\Controllers\Admin\InventoryController::class, 'reports']);
    });

    // Pre-order management
    Route::prefix('preorders')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\PreOrderController::class, 'index']);
        Route::get('/{preorder}', [App\Http\Controllers\Admin\PreOrderController::class, 'show']);
        Route::put('/{preorder}/arrival', [App\Http\Controllers\Admin\PreOrderController::class, 'updateArrival']);
        Route::put('/{preorder}/status', [App\Http\Controllers\Admin\PreOrderController::class, 'updateStatus']);
        Route::post('/{preorder}/notify', [App\Http\Controllers\Admin\PreOrderController::class, 'notifyCustomers']);
        Route::post('/bulk-update', [App\Http\Controllers\Admin\PreOrderController::class, 'bulkUpdate']);
        Route::get('/analytics/reports', [App\Http\Controllers\Admin\PreOrderController::class, 'analytics']);
    });
});