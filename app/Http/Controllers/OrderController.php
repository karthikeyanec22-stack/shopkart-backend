<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\InventoryTransaction;
use App\Models\Coupon;
use App\Models\CouponUsage;
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
            ->with(['items.product.images', 'address', 'payment', 'statusHistories', 'delivery.deliveryPartner'])
            ->latest()
            ->get();

        return response()->json([
            'orders' => $orders,
        ]);
    }

    // GET /api/orders/{id}
    public function show(Request $request, $id)
    {
        $order = Order::with(['items.product.images', 'address', 'payment', 'statusHistories', 'delivery.deliveryPartner'])
            ->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        // Verify order ownership
        if ($order->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Forbidden. You do not have permission to view this order.',
            ], 403);
        }

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
            'coupon_code' => ['nullable', 'string'],
            'items' => ['sometimes', 'array'],
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

            // 2. Fetch cart or direct items
            $cart = Cart::where('user_id', $user->id)->with('items.product')->first();
            $itemsData = [];

            if ($cart && $cart->items->count() > 0) {
                foreach ($cart->items as $cartItem) {
                    $itemsData[] = [
                        'product_id' => $cartItem->product_id,
                        'quantity' => $cartItem->quantity,
                    ];
                }
            } elseif (!empty($validated['items'])) {
                foreach ($validated['items'] as $itemData) {
                    $itemsData[] = [
                        'product_id' => $itemData['product_id'],
                        'quantity' => $itemData['quantity'],
                    ];
                }
            } else {
                return response()->json([
                    'message' => 'Your cart is empty.',
                ], 422);
            }

            // 3. Lock products and check stock availability
            $subtotal = 0;
            $itemsToProcess = [];

            foreach ($itemsData as $item) {
                $product = Product::where('id', $item['product_id'])->lockForUpdate()->first();

                if (!$product) {
                    throw new \Exception("Product ID {$item['product_id']} not found.");
                }

                if ($product->stock < $item['quantity']) {
                    return response()->json([
                        'message' => "Insufficient stock for product '{$product->name}'. Available: {$product->stock}.",
                    ], 422);
                }

                $effectivePrice = ($product->discount_price && $product->discount_price > 0 && $product->discount_price < $product->price)
                    ? $product->discount_price
                    : $product->price;

                $subtotal += $effectivePrice * $item['quantity'];

                $itemsToProcess[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'price' => $effectivePrice,
                ];
            }

            // 4. Coupon Calculation
            $discountAmount = 0;
            $couponId = null;

            if (!empty($validated['coupon_code'])) {
                $coupon = Coupon::where('code', strtoupper($validated['coupon_code']))
                    ->where('active', true)
                    ->first();

                if ($coupon) {
                    if ($subtotal >= $coupon->minimum_order_amount) {
                        if ($coupon->discount_type === 'percentage') {
                            $discountAmount = ($subtotal * $coupon->discount_value) / 100;
                            if ($coupon->maximum_discount && $discountAmount > $coupon->maximum_discount) {
                                $discountAmount = $coupon->maximum_discount;
                            }
                        } else {
                            $discountAmount = min($coupon->discount_value, $subtotal);
                        }
                        $couponId = $coupon->id;
                    }
                }
            }

            $shipping = ($subtotal - $discountAmount) > 999 ? 0 : 49;
            $tax = round(($subtotal - $discountAmount) * 0.18, 2); // 18% GST estimate
            $totalAmount = max(0, $subtotal - $discountAmount + $shipping);

            // 5. Create Order
            $orderNumber = 'SK-' . strtoupper(Str::random(8));

            $order = Order::create([
                'user_id' => $user->id,
                'address_id' => $address->id,
                'order_number' => $orderNumber,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $tax,
                'coupon_id' => $couponId,
                'shipping_amount' => $shipping,
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'delivery_status' => 'pending',
            ]);

            // 6. Create Status Timeline Entry
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'pending',
                'note' => 'Order placed successfully by customer.',
                'changed_by' => $user->name,
            ]);

            // 7. Process Items, Deduct Stock & Record Inventory Transaction
            foreach ($itemsToProcess as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ]);

                // Atomic stock decrement
                $item['product']->decrement('stock', $item['quantity']);

                InventoryTransaction::create([
                    'product_id' => $item['product']->id,
                    'type' => 'order_fulfillment',
                    'quantity' => -$item['quantity'],
                    'note' => "Stock deducted for Order #{$orderNumber}",
                    'user_id' => $user->id,
                ]);
            }

            // 8. Process Payment Record
            Payment::create([
                'order_id' => $order->id,
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'completed',
                'transaction_id' => 'TXN-' . strtoupper(Str::random(10)),
                'amount' => $totalAmount,
                'paid_at' => now(),
            ]);

            // Update status to confirmed
            $order->update(['status' => 'confirmed']);
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'confirmed',
                'note' => 'Payment received & order confirmed.',
                'changed_by' => 'System',
            ]);

            // Record Coupon Usage
            if ($couponId) {
                CouponUsage::create([
                    'coupon_id' => $couponId,
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'discount_applied' => $discountAmount,
                ]);
            }

            // Clear Cart
            if ($cart) {
                $cart->items()->delete();
            }

            $order->load(['items.product.images', 'address', 'payment', 'statusHistories']);

            return response()->json([
                'message' => 'Order placed successfully!',
                'order' => $order,
            ], 201);
        });
    }
}
