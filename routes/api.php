<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\AuthController;


use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;


// =====================================================
// AUTHENTICATION
// =====================================================

Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);


// =====================================================
// PUBLIC / CUSTOMER ROUTES
// =====================================================

// Products - Customers can view
Route::get('/products', [ProductController::class, 'index']);

Route::get('/products/{product}', [ProductController::class, 'show']);


// Categories - Customers can view
Route::get('/categories', [CategoryController::class, 'index']);

Route::get('/categories/{category}', [CategoryController::class, 'show']);


// =====================================================
// AUTHENTICATED USER ROUTES
// =====================================================

Route::middleware('auth:sanctum')->group(function () {

    // Get currently logged-in user
    Route::get('/me', [AuthController::class, 'me']);

    // Logout
    Route::post('/logout', [AuthController::class, 'logout']);

    // Cart Management
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/items', [CartController::class, 'store']);
    Route::put('/cart/items/{id}', [CartController::class, 'update']);
    Route::delete('/cart/items/{id}', [CartController::class, 'destroy']);
    Route::delete('/cart', [CartController::class, 'clear']);

    // Orders Management
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
});


// =====================================================
// ADMIN ROUTES
// =====================================================

Route::middleware(['auth:sanctum', 'admin'])->group(function () {

    // -------------------------------------------------
    // PRODUCT MANAGEMENT
    // -------------------------------------------------

    // Create product
    Route::post('/products', [ProductController::class, 'store']);

    // Update product
    Route::put('/products/{product}', [ProductController::class, 'update']);

    // Partial update product
    Route::patch('/products/{product}', [ProductController::class, 'update']);

    // Delete product
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);


    // -------------------------------------------------
    // CATEGORY MANAGEMENT
    // -------------------------------------------------

    // Create category
    Route::post('/categories', [CategoryController::class, 'store']);

    // Update category
    Route::put('/categories/{category}', [CategoryController::class, 'update']);

    // Partial update category
    Route::patch('/categories/{category}', [CategoryController::class, 'update']);

    // Delete category
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);


    // -------------------------------------------------
    // PRODUCT IMAGE MANAGEMENT
    // -------------------------------------------------

    // Upload product image
    Route::post(
        '/products/{product}/images',
        [ProductImageController::class, 'store']
    );

    // Delete product image
    Route::delete(
        '/products/{product}/images/{image}',
        [ProductImageController::class, 'destroy']
    );


    // -------------------------------------------------
    // ADMIN TEST
    // -------------------------------------------------

    Route::get('/admin/test', function () {

        return response()->json([
            'message' => 'Welcome Admin',
        ]);

    });

});