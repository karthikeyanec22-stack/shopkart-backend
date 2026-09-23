<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    // GET /api/admin/orders
    public function index(Request $request)
    {
        $query = Order::with(['user', 'address', 'payment', 'delivery.deliveryPartner', 'items']);

        // Search by Order ID / Number / Customer Name / Email / Phone
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('id', $search)
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        // Status Filter
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        // Payment Status Filter
        if ($request->filled('payment_status') && $request->input('payment_status') !== 'all') {
            $query->whereHas('payment', function ($pq) use ($request) {
                $pq->where('payment_status', $request->input('payment_status'));
            });
        }

        $orders = $query->latest()->paginate((int) $request->input('per_page', 20));

        return response()->json($orders);
    }

    // GET /api/admin/orders/{id}
    public function show($id)
    {
        $order = Order::with([
            'user',
            'address',
            'payment',
            'items.product.images',
            'statusHistories',
            'delivery.deliveryPartner',
            'coupon',
        ])->findOrFail($id);

        return response()->json(['order' => $order]);
    }

    // PUT /api/admin/orders/{id}/status
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,confirmed,processing,packed,shipped,out_for_delivery,delivered,cancelled,return_requested,returned,refund_initiated,refunded'],
            'note' => ['nullable', 'string'],
        ]);

        $order = Order::findOrFail($id);

        $order->update([
            'status' => $validated['status'],
        ]);

        // Record history timeline
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => $validated['status'],
            'note' => $validated['note'] ?? "Order status updated to {$validated['status']} by Admin",
            'changed_by' => $request->user()->name ?? 'Admin',
        ]);

        $order->load(['user', 'address', 'payment', 'statusHistories', 'delivery.deliveryPartner', 'items.product.images']);

        return response()->json([
            'message' => 'Order status updated successfully.',
            'order' => $order,
        ]);
    }
}
