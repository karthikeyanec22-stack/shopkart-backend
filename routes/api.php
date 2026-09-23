<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminProductController;
use App\Http\Controllers\AdminCategoryController;
use App\Http\Controllers\AdminCustomerController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AdminDeliveryController;
use App\Http\Controllers\AdminPaymentController;
use App\Http\Controllers\AdminInventoryController;
use App\Http\Controllers\AdminCouponController;
use App\Http\Controllers\AdminReviewController;
use App\Http\Controllers\AdminReportController;

// =====================================================
// AUTHENTICATION & PUBLIC CUSTOMER ROUTES
// =====================================================

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public Storefront Browsing
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);

// Coupon Validation for Customer Checkout
Route::post('/coupons/validate', [AdminCouponController::class, 'validateCoupon']);

// =====================================================
// AUTHENTICATED CUSTOMER ROUTES
// =====================================================

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Cart Management
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/items', [CartController::class, 'store']);
    Route::put('/cart/items/{id}', [CartController::class, 'update']);
    Route::delete('/cart/items/{id}', [CartController::class, 'destroy']);
    Route::delete('/cart', [CartController::class, 'clear']);

    // Customer Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
});

// =====================================================
// ADMIN PROTECTED ROUTES (Requires auth:sanctum & admin)
// =====================================================

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // 1. Dashboard API
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);

    // 2. Product Management CRUD
    Route::get('/products', [AdminProductController::class, 'index']);
    Route::post('/products', [AdminProductController::class, 'store']);
    Route::get('/products/{id}', [AdminProductController::class, 'show']);
    Route::put('/products/{id}', [AdminProductController::class, 'update']);
    Route::delete('/products/{id}', [AdminProductController::class, 'destroy']);
    Route::post('/products/{product}/images', [ProductImageController::class, 'store']);
    Route::delete('/products/{product}/images/{image}', [ProductImageController::class, 'destroy']);

    // 3. Category Management CRUD
    Route::get('/categories', [AdminCategoryController::class, 'index']);
    Route::post('/categories', [AdminCategoryController::class, 'store']);
    Route::put('/categories/{id}', [AdminCategoryController::class, 'update']);
    Route::delete('/categories/{id}', [AdminCategoryController::class, 'destroy']);

    // 4. Customer Management
    Route::get('/customers', [AdminCustomerController::class, 'index']);
    Route::get('/customers/{id}', [AdminCustomerController::class, 'show']);
    Route::put('/customers/{id}/status', [AdminCustomerController::class, 'updateStatus']);

    // 5. Order Management & Workflow
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
    Route::put('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);

    // 6. Delivery Management & Partner CRUD
    Route::get('/delivery-partners', [AdminDeliveryController::class, 'indexPartners']);
    Route::post('/delivery-partners', [AdminDeliveryController::class, 'storePartner']);
    Route::put('/delivery-partners/{id}', [AdminDeliveryController::class, 'updatePartner']);
    Route::get('/deliveries', [AdminDeliveryController::class, 'indexDeliveries']);
    Route::post('/orders/{id}/assign-delivery', [AdminDeliveryController::class, 'assignDelivery']);

    // 7. Payment Audit Logs
    Route::get('/payments', [AdminPaymentController::class, 'index']);

    // 8. Inventory & Stock Adjustments
    Route::get('/inventory', [AdminInventoryController::class, 'index']);
    Route::post('/inventory/adjust', [AdminInventoryController::class, 'adjustStock']);
    Route::get('/inventory/history', [AdminInventoryController::class, 'history']);

    // 9. Coupon Management CRUD
    Route::get('/coupons', [AdminCouponController::class, 'index']);
    Route::post('/coupons', [AdminCouponController::class, 'store']);
    Route::put('/coupons/{id}', [AdminCouponController::class, 'update']);
    Route::delete('/coupons/{id}', [AdminCouponController::class, 'destroy']);

    // 10. Review Moderation
    Route::get('/reviews', [AdminReviewController::class, 'index']);
    Route::delete('/reviews/{id}', [AdminReviewController::class, 'destroy']);

    // 11. Reports & Analytics
    Route::get('/reports', [AdminReportController::class, 'index']);
});