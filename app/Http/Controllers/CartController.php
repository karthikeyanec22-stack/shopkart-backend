<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    // GET /api/cart
    public function index(Request $request)
    {
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);
        $cart->load('items.product.images', 'items.product.category');

        return response()->json([
            'cart' => $cart,
        ]);
    }

    // POST /api/cart/items
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
        ]);

        $quantity = $validated['quantity'] ?? 1;
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);

        $product = Product::findOrFail($validated['product_id']);

        $item = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->first();

        if ($item) {
            $item->quantity += $quantity;
            $item->save();
        } else {
            $item = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
            ]);
        }

        $cart->load('items.product.images', 'items.product.category');

        return response()->json([
            'message' => 'Product added to cart',
            'cart' => $cart,
        ], 200);
    }

    // PUT /api/cart/items/{id}
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = Cart::where('user_id', $request->user()->id)->firstOrFail();
        $item = CartItem::where('cart_id', $cart->id)->where('id', $id)->firstOrFail();

        $item->update(['quantity' => $validated['quantity']]);

        $cart->load('items.product.images', 'items.product.category');

        return response()->json([
            'message' => 'Cart updated successfully',
            'cart' => $cart,
        ]);
    }

    // DELETE /api/cart/items/{id}
    public function destroy(Request $request, $id)
    {
        $cart = Cart::where('user_id', $request->user()->id)->firstOrFail();
        $item = CartItem::where('cart_id', $cart->id)->where('id', $id)->firstOrFail();

        $item->delete();

        $cart->load('items.product.images', 'items.product.category');

        return response()->json([
            'message' => 'Item removed from cart',
            'cart' => $cart,
        ]);
    }

    // DELETE /api/cart
    public function clear(Request $request)
    {
        $cart = Cart::where('user_id', $request->user()->id)->first();
        if ($cart) {
            $cart->items()->delete();
        }

        return response()->json([
            'message' => 'Cart cleared',
            'cart' => $cart ? $cart->load('items') : null,
        ]);
    }
}
