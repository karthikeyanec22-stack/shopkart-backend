<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    // GET /api/orders
    public function index(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with(['items.product.images', 'address', 'payment'])
            ->latest()
            ->get();

        return response()->json([
            'orders' => $orders,
        ]);
    }

    // GET /api/orders/{id}
    public function show(Request $request, $id)
    {
        $order = Order::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->with(['items.product.images', 'address', 'payment'])
            ->firstOrFail();

        return response()->json([
            'order' => $order,
        ]);
    }

    // POST /api/orders
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['sometimes', 'string', 'max:255'],
            'payment_method' => ['required', 'string'],
            'items' => ['sometimes', 'array'], // optional override if cart is empty or frontend passes direct items
        ]);

        $user = $request->user();

        return DB::transaction(function () use ($validated, $user) {
            // 1. Save Address
            $address = Address::create([
                'user_id' => $user->id,
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'address_line1' => $validated['address_line1'],
                'address_line2' => $validated['address_line2'] ?? null,
                'city' => $validated['city'],
                'state' => $validated['state'],
                'postal_code' => $validated['postal_code'],
                'country' => $validated['country'] ?? 'India',
                'is_default' => true,
            ]);

            // 2. Fetch cart or items
            $cart = Cart::where('user_id', $user->id)->with('items.product')->first();
            $itemsToProcess = [];

            if ($cart && $cart->items->count() > 0) {
                foreach ($cart->items as $cartItem) {
                    $itemsToProcess[] = [
                        'product' => $cartItem->product,
                        'quantity' => $cartItem->quantity,
                        'price' => $cartItem->product->price,
                    ];
                }
            } elseif (!empty($validated['items'])) {
                foreach ($validated['items'] as $itemData) {
                    $product = Product::findOrFail($itemData['product_id']);
                    $itemsToProcess[] = [
                        'product' => $product,
                        'quantity' => $itemData['quantity'],
                        'price' => $product->price,
                    ];
                }
            } else {
                return response()->json([
                    'message' => 'Your cart is empty.',
                ], 400);
            }

            // 3. Calculate subtotal & totals
            $subtotal = 0;
            foreach ($itemsToProcess as $item) {
                $subtotal += $item['price'] * $item['quantity'];
            }

            $shipping = $subtotal > 999 ? 0 : 49;
            $totalAmount = $subtotal + $shipping;

            // 4. Create Order
            $orderNumber = 'SK-' . strtoupper(Str::random(8));

            $order = Order::create([
                'user_id' => $user->id,
                'address_id' => $address->id,
                'order_number' => $orderNumber,
                'subtotal' => $subtotal,
                'shipping_amount' => $shipping,
                'total_amount' => $totalAmount,
                'status' => 'processing',
            ]);

            // 5. Create Order Items & Update Stock
            foreach ($itemsToProcess as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ]);

                // Reduce stock
                if ($item['product']->stock >= $item['quantity']) {
                    $item['product']->decrement('stock', $item['quantity']);
                }
            }

            // 6. Create Payment Record
            Payment::create([
                'order_id' => $order->id,
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'completed',
                'transaction_id' => 'TXN-' . strtoupper(Str::random(10)),
                'amount' => $totalAmount,
                'paid_at' => now(),
            ]);

            // 7. Clear cart
            if ($cart) {
                $cart->items()->delete();
            }

            $order->load(['items.product.images', 'address', 'payment']);

            return response()->json([
                'message' => 'Order placed successfully!',
                'order' => $order,
            ], 201);
        });
    }
}
